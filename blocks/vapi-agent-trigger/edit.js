(function (wp) {
	const { __ } = wp.i18n;
	const { registerBlockType } = wp.blocks;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, Notice } = wp.components;
	const { useEffect } = wp.element;

	const variationClassMap = {
		default: 'is-design-default',
		'elevation-high': 'is-design-elevation-high',
		tonal: 'is-design-tonal',
		outlined: 'is-design-outlined',
		glassmorphism: 'is-design-glassmorphism',
		minimalist: 'is-design-minimalist',
		neumorphic: 'is-design-neumorphic',
	};

	registerBlockType('nexdine/vapi-agent-trigger', {
		edit: function Edit(props) {
			const { attributes, setAttributes } = props;
			const { assistantId, designVariation } = attributes;
			const blockData = window.nexdineVapiBlockData || {};
			const agents = Array.isArray(blockData.agents) ? blockData.agents : [];
			const defaultAssistantId = blockData.defaultAssistantId || '';
			const message = blockData.message || '';
			const designClass = variationClassMap[designVariation] || variationClassMap.default;
			const blockProps = useBlockProps({ className: `nexdine-vapi-agent-trigger ${designClass}` });

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
						InspectorControls,
						null,
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
								'button',
								{ type: 'button', className: 'nexdine-vapi-agent-trigger__button is-online' },
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
