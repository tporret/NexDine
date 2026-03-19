(function (wp) {
	const { __ } = wp.i18n;
	const { registerBlockType } = wp.blocks;
	const { BlockControls, InspectorControls, AlignmentToolbar, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, TextControl, Notice } = wp.components;
	const { useEffect } = wp.element;
	const MAX_BUTTON_TEXT_LENGTH = 32;

	const variationClassMap = {
		default: 'is-design-default',
		'elevation-high': 'is-design-elevation-high',
		tonal: 'is-design-tonal',
		outlined: 'is-design-outlined',
		glassmorphism: 'is-design-glassmorphism',
		minimalist: 'is-design-minimalist',
		neumorphic: 'is-design-neumorphic',
	};

	const alignmentClassMap = {
		left: 'is-horizontal-left',
		center: 'is-horizontal-center',
		right: 'is-horizontal-right',
	};

	registerBlockType('nexdine/vapi-agent-trigger', {
		edit: function Edit(props) {
			const { attributes, setAttributes } = props;
			const { assistantId, buttonText, designVariation, horizontalAlign } = attributes;
			const blockData = window.nexdineVapiBlockData || {};
			const agents = Array.isArray(blockData.agents) ? blockData.agents : [];
			const defaultAssistantId = blockData.defaultAssistantId || '';
			const message = blockData.message || '';
			const designClass = variationClassMap[designVariation] || variationClassMap.default;
			const alignClass = alignmentClassMap[horizontalAlign] || alignmentClassMap.left;
			const blockProps = useBlockProps({ className: `nexdine-vapi-agent-trigger ${designClass} ${alignClass}` });

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

			const selectedAgent = agents.find(function getSelected(agent) {
				return agent && agent.id === assistantId;
			});

			const resolvedButtonText = (buttonText || '').trim() || __('Talk To Host', 'nexdine');
			const textLength = (buttonText || '').length;

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
						BlockControls,
						null,
						wp.element.createElement(AlignmentToolbar, {
							value: horizontalAlign || 'left',
							onChange: function onAlignChange(value) {
								setAttributes({ horizontalAlign: value || 'left' });
							},
						})
					),
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
						InspectorControls,
						null,
						wp.element.createElement(
							PanelBody,
							{ title: __('Content', 'nexdine'), initialOpen: false },
							wp.element.createElement(TextControl, {
								label: __('Button Text', 'nexdine'),
								value: buttonText || '',
								onChange: function onButtonTextChange(value) {
									setAttributes({ buttonText: value.slice(0, MAX_BUTTON_TEXT_LENGTH) });
								},
								help: __('Updates only the trigger button label. ' + (textLength > MAX_BUTTON_TEXT_LENGTH ? MAX_BUTTON_TEXT_LENGTH : textLength) + '/' + MAX_BUTTON_TEXT_LENGTH, 'nexdine'),
							})
						),
						wp.element.createElement(
							PanelBody,
							{ title: __('Design Style', 'nexdine'), initialOpen: false },
							wp.element.createElement(SelectControl, {
								label: __('Design Variation', 'nexdine'),
								value: designVariation || 'default',
								options: [
									{ label: __('Default (Material 3)', 'nexdine'), value: 'default' },
									{ label: __('Elevation High', 'nexdine'), value: 'elevation-high' },
									{ label: __('Tonal', 'nexdine'), value: 'tonal' },
									{ label: __('Outlined', 'nexdine'), value: 'outlined' },
									{ label: __('Glassmorphism', 'nexdine'), value: 'glassmorphism' },
									{ label: __('Minimalist', 'nexdine'), value: 'minimalist' },
									{ label: __('Neumorphic', 'nexdine'), value: 'neumorphic' },
								],
								onChange: function onDesignChange(value) {
									setAttributes({ designVariation: value || 'default' });
								},
								help: __('Choose a visual style for the trigger controls.', 'nexdine'),
							})
						),
						wp.element.createElement(
							PanelBody,
							{ title: __('Layout', 'nexdine'), initialOpen: false },
							wp.element.createElement(SelectControl, {
								label: __('Horizontal Alignment', 'nexdine'),
								value: horizontalAlign || 'left',
								options: [
									{ label: __('Left', 'nexdine'), value: 'left' },
									{ label: __('Center', 'nexdine'), value: 'center' },
									{ label: __('Right', 'nexdine'), value: 'right' },
								],
								onChange: function onSidebarAlignChange(value) {
									setAttributes({ horizontalAlign: value || 'left' });
								},
								help: __('Also available in the block toolbar.', 'nexdine'),
							})
						)
					),
					wp.element.createElement(
						'div',
						blockProps,
						wp.element.createElement(
							'div',
							{ className: 'nexdine-vapi-agent-trigger__editor-preview' },
							wp.element.createElement(
								'p',
								{ className: 'nexdine-vapi-agent-trigger__title' },
								__('Vapi Agent Trigger Preview', 'nexdine')
							),
							wp.element.createElement(
								'p',
								{ className: 'nexdine-vapi-agent-trigger__subtitle' },
								selectedAgent
									? __('Users will start a voice call with the selected agent.', 'nexdine')
									: __('Select an agent in the sidebar to activate this block.', 'nexdine')
							),
							wp.element.createElement(
								'div',
								{ className: 'nexdine-vapi-agent-trigger__controls' },
								wp.element.createElement(
									'button',
									{ type: 'button', className: 'nexdine-vapi-agent-trigger__button is-online' },
									resolvedButtonText
								),
								wp.element.createElement(
									'button',
									{ type: 'button', className: 'nexdine-vapi-agent-trigger__end-button is-hidden' },
									__('End Call', 'nexdine')
								)
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
