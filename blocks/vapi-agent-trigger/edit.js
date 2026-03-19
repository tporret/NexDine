(function (wp) {
	const { __ } = wp.i18n;
	const { registerBlockType } = wp.blocks;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, Notice } = wp.components;
	const { useEffect } = wp.element;

	registerBlockType('nexdine/vapi-agent-trigger', {
		edit: function Edit(props) {
			const { attributes, setAttributes } = props;
			const { assistantId } = attributes;
			const blockData = window.nexdineVapiBlockData || {};
			const agents = Array.isArray(blockData.agents) ? blockData.agents : [];
			const defaultAssistantId = blockData.defaultAssistantId || '';
			const message = blockData.message || '';
			const blockProps = useBlockProps({ className: 'nexdine-vapi-agent-trigger' });

			useEffect(
				function setDefaultAgent() {
					if (!assistantId && defaultAssistantId) {
						const hasDefault = agents.some(function hasAgent(agent) {
							return agent && agent.id === defaultAssistantId;
						});

						if (hasDefault) {
							setAttributes({ assistantId: defaultAssistantId });
						}
					}
				},
				[assistantId, defaultAssistantId, agents, setAttributes]
			);

			const previewStyle = {
				border: '1px solid #e2e8f0',
				borderRadius: '12px',
				padding: '16px',
				background: '#ffffff',
				boxShadow: '0 2px 10px rgba(15, 23, 42, 0.05)',
			};

			const selectedAgent = agents.find(function getSelected(agent) {
				return agent && agent.id === assistantId;
			});

			const selectOptions = [
				{ label: __('Select an agent', 'nexdine'), value: '' },
			].concat(
				agents.map(function mapAgent(agent) {
					return {
						label: agent.name,
						value: agent.id,
					};
				})
			);

			return (
				wp.element.createElement(
					wp.element.Fragment,
					null,
					wp.element.createElement(
						InspectorControls,
						null,
						wp.element.createElement(
							PanelBody,
							{ title: __('Agent Selection', 'nexdine'), initialOpen: true },
							wp.element.createElement(SelectControl, {
								label: __('Available Agents', 'nexdine'),
								value: assistantId || '',
								options: selectOptions,
								onChange: function onChange(value) {
									setAttributes({ assistantId: value });
								},
								help: __('Agents are loaded from NexDine Vapi settings.', 'nexdine'),
							})
						)
					),
					wp.element.createElement(
						'div',
						blockProps,
						wp.element.createElement(
							'div',
							{ style: previewStyle, className: 'tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-p-4' },
							wp.element.createElement(
								'p',
								{ style: { marginTop: 0, marginBottom: '8px', fontWeight: 600, color: '#0f172a' } },
								__('Vapi Agent Trigger Preview', 'nexdine')
							),
							wp.element.createElement(
								'p',
								{ style: { marginTop: 0, marginBottom: '12px', color: '#475569', fontSize: '13px' } },
								selectedAgent
									? __('Users will start a voice call with the selected agent.', 'nexdine')
									: __('Select an agent in the sidebar to activate this block.', 'nexdine')
							),
							wp.element.createElement(
								'button',
								{
									type: 'button',
									style: {
										backgroundColor: '#0d9488',
										color: '#ffffff',
										border: 'none',
										borderRadius: '999px',
										padding: '0.75rem 1.25rem',
										fontWeight: 600,
										cursor: 'pointer',
									},
								},
								__('Talk To Host', 'nexdine')
							),
							message
								? wp.element.createElement(Notice, {
									status: 'warning',
									isDismissible: false,
									children: message,
								})
								: null,
							!assistantId
								? wp.element.createElement(Notice, {
									status: 'warning',
									isDismissible: false,
									children: __('Please choose an available agent.', 'nexdine'),
								})
								: null
						)
					)
				)
			);
		},
		save: function Save() {
			return null;
		},
	});
})(window.wp);
