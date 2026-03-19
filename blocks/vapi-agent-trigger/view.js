(() => {
	const rootSelector = '.nexdine-vapi-agent-trigger';
	let sdkLoadPromise = null;
	const activeClients = new WeakMap();

	const getButtonNodes = () => Array.from(document.querySelectorAll(`${rootSelector} .nexdine-vapi-agent-trigger__button`));

	const checkAvailability = async (assistantId) => {
		await new Promise((resolve) => {
			window.setTimeout(resolve, 350);
		});

		if (!assistantId) {
			return false;
		}

		if (assistantId.toLowerCase().includes('offline')) {
			return false;
		}

		return true;
	};

	const loadVapiSdk = async () => {
		if (window.Vapi || (window.vapiSDK && typeof window.vapiSDK.create === 'function')) {
			return true;
		}

		if (!sdkLoadPromise) {
			sdkLoadPromise = (async () => {
				const moduleCandidates = [
					'https://cdn.jsdelivr.net/npm/@vapi-ai/web@latest/+esm',
					'https://esm.sh/@vapi-ai/web',
					'https://cdn.skypack.dev/@vapi-ai/web',
				];

				for (const src of moduleCandidates) {
					try {
						const module = await import(/* webpackIgnore: true */ src);
						const VapiCtor = module && (module.default || module.Vapi || module);

						if (typeof VapiCtor === 'function') {
							window.Vapi = VapiCtor;
							return true;
						}
					} catch (error) {
						window.console.warn('NexDine: Failed loading Vapi SDK ESM source.', src, error);
					}
				}

				return false;
			})();
		}

		return sdkLoadPromise;
	};

	const createVapiClient = async (publicKey) => {
		if (!publicKey) {
			return null;
		}

		const sdkReady = await loadVapiSdk();

		if (!sdkReady) {
			return null;
		}

		if (window.Vapi && typeof window.Vapi === 'function') {
			return new window.Vapi(publicKey);
		}

		if (window.vapiSDK && typeof window.vapiSDK.create === 'function') {
			return window.vapiSDK.create({ apiKey: publicKey });
		}

		return null;
	};

	const setLiveState = (startButton, endButton, isLive) => {
		const defaultText = startButton.dataset.defaultText || 'Talk To Host';
		const liveText = startButton.dataset.liveText || 'Call Live';

		if (isLive) {
			startButton.textContent = liveText;
			startButton.disabled = true;
			startButton.setAttribute('aria-disabled', 'true');
			startButton.classList.remove('is-loading', 'is-offline');
			startButton.classList.add('is-online');

			if (endButton) {
				endButton.classList.remove('is-hidden');
				endButton.disabled = false;
			}

			return;
		}

		startButton.textContent = defaultText;
		startButton.disabled = false;
		startButton.setAttribute('aria-disabled', 'false');
		startButton.classList.remove('is-loading', 'is-offline');
		startButton.classList.add('is-online');

		if (endButton) {
			endButton.classList.add('is-hidden');
			endButton.disabled = false;
		}
	};

	const bindClientEvents = (client, startButton, endButton) => {
		if (!client || typeof client.on !== 'function') {
			return;
		}

		const liveEvents = ['call-start', 'callStart', 'session-start', 'sessionStart', 'conversation-start', 'connected'];
		const endedEvents = ['call-end', 'callEnd', 'session-end', 'sessionEnd', 'conversation-end', 'disconnected'];

		liveEvents.forEach((eventName) => {
			try {
				client.on(eventName, () => {
					setLiveState(startButton, endButton, true);
				});
			} catch (error) {
				// Ignore unsupported event names from different SDK versions.
			}
		});

		endedEvents.forEach((eventName) => {
			try {
				client.on(eventName, () => {
					activeClients.delete(startButton);
					setLiveState(startButton, endButton, false);
				});
			} catch (error) {
				// Ignore unsupported event names from different SDK versions.
			}
		});

		try {
			client.on('error', () => {
				activeClients.delete(startButton);
				setLiveState(startButton, endButton, false);
			});
		} catch (error) {
			// Ignore when error event is unavailable.
		}
	};

	const stopClientSession = async (client) => {
		if (!client) {
			return;
		}

		const stopMethods = ['stop', 'end', 'hangup', 'disconnect'];

		for (const methodName of stopMethods) {
			if (typeof client[methodName] === 'function') {
				await client[methodName]();
				return;
			}
		}

		throw new Error('No supported end-call method found on Vapi client.');
	};

	const endSession = async (startButton, endButton) => {
		const client = activeClients.get(startButton);

		if (!client) {
			setLiveState(startButton, endButton, false);
			return;
		}

		if (endButton) {
			endButton.disabled = true;
		}

		try {
			await stopClientSession(client);
		} catch (error) {
			window.console.warn('NexDine: Unable to end Vapi session cleanly.', error);
		}

		activeClients.delete(startButton);
		setLiveState(startButton, endButton, false);
	};

	const startSession = async (button) => {
		const wrapper = button.closest(rootSelector);
		const endButton = wrapper ? wrapper.querySelector('.nexdine-vapi-agent-trigger__end-button') : null;
		const assistantId = button.dataset.assistantId || '';
		const publicKey = button.dataset.apiKey || '';
		const mode = button.dataset.mode || 'voice';
		const defaultText = button.dataset.defaultText || 'Talk To Host';
		const offlineText = button.dataset.offlineText || 'Agent Offline';

		button.disabled = true;
		button.classList.add('is-loading');

		const isOnline = await checkAvailability(assistantId);

		if (!isOnline) {
			button.textContent = offlineText;
			button.setAttribute('aria-disabled', 'true');
			button.classList.remove('is-loading', 'is-online');
			button.classList.add('is-offline');

			if (endButton) {
				endButton.classList.add('is-hidden');
			}

			return;
		}

		button.classList.remove('is-loading', 'is-offline');
		button.classList.add('is-online');
		button.textContent = defaultText;
		button.disabled = false;
		button.setAttribute('aria-disabled', 'false');

		const vapiClient = await createVapiClient(publicKey);

		if (!vapiClient) {
			button.disabled = false;
			button.setAttribute('aria-disabled', 'false');
			window.console.warn('NexDine: Vapi Web SDK is unavailable.');
			return;
		}

		activeClients.set(button, vapiClient);
		bindClientEvents(vapiClient, button, endButton);

		try {
			if (mode === 'chat' && typeof vapiClient.open === 'function') {
				vapiClient.open({ assistantId, mode: 'chat' });
				setLiveState(button, endButton, true);
				return;
			}

			if (typeof vapiClient.start === 'function') {
				await vapiClient.start(assistantId);
				setLiveState(button, endButton, true);
				return;
			}

			if (typeof vapiClient.open === 'function') {
				vapiClient.open({ assistantId, mode: 'voice' });
				setLiveState(button, endButton, true);
				return;
			}

			window.console.warn('NexDine: No compatible method found on Vapi client.');
			activeClients.delete(button);
			setLiveState(button, endButton, false);
		} catch (error) {
			activeClients.delete(button);
			setLiveState(button, endButton, false);
			window.console.error('NexDine: Unable to start Vapi session.', error);
		}

		button.textContent = defaultText;
	};

	const bootstrapButtons = () => {
		getButtonNodes().forEach((button) => {
			if (button.dataset.bound === 'true') {
				return;
			}

			button.dataset.bound = 'true';
			const wrapper = button.closest(rootSelector);
			const endButton = wrapper ? wrapper.querySelector('.nexdine-vapi-agent-trigger__end-button') : null;

			setLiveState(button, endButton, false);

			button.addEventListener('click', () => {
				startSession(button);
			});

			if (endButton && endButton.dataset.bound !== 'true') {
				endButton.dataset.bound = 'true';
				endButton.addEventListener('click', () => {
					endSession(button, endButton);
				});
			}
		});
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bootstrapButtons);
	} else {
		bootstrapButtons();
	}
})();
