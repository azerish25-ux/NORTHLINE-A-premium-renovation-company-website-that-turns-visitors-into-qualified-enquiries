/* NORTHLINE dynamic blocks: editable controls, native WordPress media and previews. */
(function (wp) {
  'use strict';
  const h = wp.element.createElement;
  const { registerBlockType } = wp.blocks;
  const { InspectorControls, MediaUpload, MediaUploadCheck, useBlockProps } = wp.blockEditor;
  const { PanelBody, TextControl, SelectControl, RangeControl, ToggleControl, Button, Placeholder } = wp.components;
  const ServerSideRender = wp.serverSideRender.default || wp.serverSideRender;
  const definitions = {
    planner: { title: 'NORTHLINE project planner', attributes: { mode: { type: 'string', default: 'planner' } } },
    projects: { title: 'NORTHLINE project gallery', attributes: { limit: { type: 'number', default: 6 }, filters: { type: 'boolean', default: true } } },
    comparison: { title: 'NORTHLINE before & after', attributes: { before: { type: 'string', default: '' }, after: { type: 'string', default: '' }, label: { type: 'string', default: 'Matched architectural view' } } },
    materials: { title: 'NORTHLINE material library', attributes: { heading: { type: 'string', default: 'A few things, chosen well.' } } },
    plan: { title: 'NORTHLINE concept floor plan', attributes: { label: { type: 'string', default: 'Birch House / Ground floor' } } }
  };
  Object.entries(definitions).forEach(([name, definition]) => registerBlockType('northline/' + name, {
    apiVersion: 3, category: 'design', icon: 'building', ...definition,
    supports: { html: false, multiple: name !== 'planner' },
    edit({ attributes, setAttributes }) {
      const controls = [];
      if (name === 'planner') controls.push(h(SelectControl, { key: 'mode', label: 'Workspace', value: attributes.mode, options: [{ label: 'Project planner', value: 'planner' }, { label: 'Private consultation desk', value: 'consultation' }], onChange: mode => setAttributes({ mode }) }));
      if (name === 'projects') controls.push(h(RangeControl, { key: 'limit', label: 'Number of projects', min: 1, max: 60, value: attributes.limit, onChange: limit => setAttributes({ limit }) }), h(ToggleControl, { key: 'filters', label: 'Show category filters', checked: attributes.filters, onChange: filters => setAttributes({ filters }) }));
      if ('heading' in attributes) controls.push(h(TextControl, { key: 'heading', label: 'Section heading', value: attributes.heading, onChange: heading => setAttributes({ heading }) }));
      if ('label' in attributes) controls.push(h(TextControl, { key: 'label', label: 'Caption / accessible label', value: attributes.label, onChange: label => setAttributes({ label }) }));
      if (name === 'comparison') ['before', 'after'].forEach(key => controls.push(h('div', { key }, h(TextControl, { label: key + ' image URL', value: attributes[key], onChange: value => setAttributes({ [key]: value }) }), h(MediaUploadCheck, {}, h(MediaUpload, { allowedTypes: ['image'], onSelect: media => setAttributes({ [key]: media.url }), render: ({ open }) => h(Button, { variant: 'secondary', onClick: open }, 'Choose ' + key + ' image') })))));
      return h('div', useBlockProps(), h(InspectorControls, {}, h(PanelBody, { title: definition.title }, controls)), name === 'planner' ? h(Placeholder, { label: definition.title, instructions: 'The live website loads the accessible, save-first project workflow. Use the inspector to choose the planner or consultation workspace.' }) : h(ServerSideRender, { block: 'northline/' + name, attributes }));
    },
    save() { return null; }
  }));
})(window.wp);
