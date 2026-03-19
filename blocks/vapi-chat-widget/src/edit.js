import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function Edit({ attributes, setAttributes }) {
	const { assistantId } = attributes;
	const [assistantOptions, setAssistantOptions] = useState([]);
	const [isLoading, setIsLoading] = useState(false);
	const [requestError, setRequestError] = useState('');
	const blockData = window.nexdineVapiChatWidgetData || {};

	useEffect(() => {
		let isMounted = true;

		setIsLoading(true);
		setRequestError('');

		apiFetch({
			path: '/nexdine/v1/vapi-assistants',
			method: 'GET',
		})
			.then((response) => {
				if (!isMounted) {
					return;
				}

				const options = (Array.isArray(response?.assistants) ? response.assistants : [])
					.map((item) => ({
						label: item?.name || item?.id || '',
						value: item?.id || '',
					}))
					.filter((item) => item.value !== '');

				setAssistantOptions(options);

				if (!assistantId && options.length > 0) {
					setAttributes({
						assistantId: blockData.defaultAssistantId || options[0].value,
					});
				}
			})
			.catch((error) => {
				if (!isMounted) {
					return;
				}

				setAssistantOptions([]);
				setRequestError(error?.message || __('Unable to load assistants from Vapi.', 'nexdine'));
			})
			.finally(() => {
				if (isMounted) {
					setIsLoading(false);
				}
			});

		return () => {
			isMounted = false;
		};
	}, [assistantId, setAttributes, blockData.defaultAssistantId]);

	const options = [
		{ label: __('Select an assistant', 'nexdine'), value: '' },
		...assistantOptions,
	];

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Vapi Chat Widget Settings', 'nexdine')} initialOpen={true}>
					<SelectControl
						label={__('Assistant', 'nexdine')}
						value={assistantId || ''}
						options={options}
						onChange={(value) => setAttributes({ assistantId: value })}
						disabled={isLoading}
						help={isLoading ? __('Loading assistants...', 'nexdine') : __('Select the assistant to embed.', 'nexdine')}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...useBlockProps()}>
				<p><strong>{__('Vapi Chat Widget', 'nexdine')}</strong></p>
				<p>{assistantId ? __('Assistant selected and ready for render.', 'nexdine') : __('Choose an assistant in the block sidebar.', 'nexdine')}</p>
				{blockData.message ? <Notice status="warning" isDismissible={false}>{blockData.message}</Notice> : null}
				{requestError ? <Notice status="error" isDismissible={false}>{requestError}</Notice> : null}
			</div>
		</>
	);
}
