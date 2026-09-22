/* Qpedia dynamic homepage blocks — no build step required. */
(function (blocks, element, blockEditor, components, i18n) {
	'use strict';

	if (!blocks || !element || !blockEditor || !components || !i18n) {
		return;
	}

	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var RangeControl = components.RangeControl;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;
	var domain = 'quantum-pedia-child';

	function preview(icon, title, lines) {
		var children = [
			el('div', { className: 'qp-block-ph__icon', key: 'icon', 'aria-hidden': true }, icon),
			el('strong', { className: 'qp-block-ph__title', key: 'title' }, title)
		];

		(lines || []).forEach(function (line, index) {
			if (line) {
				children.push(el('p', { className: 'qp-block-ph__line', key: 'line-' + index }, line));
			}
		});
		children.push(el('p', { className: 'qp-block-ph__hint', key: 'hint' }, __('تنظیمات این بلوک در ستون کناری است.', domain)));
		return el('div', { className: 'qp-block-ph' }, children);
	}

	function updateAttribute(props, key) {
		return function (value) {
			var update = {};
			update[key] = value;
			props.setAttributes(update);
		};
	}

	blocks.registerBlockType('qp/hero', {
		title: __('هیروی صفحه اصلی', domain),
		description: __('تیتر و توضیح اصلی دانشنامه.', domain),
		icon: 'cover-image',
		category: 'widgets',
		keywords: [__('صفحه اصلی', domain), __('هیرو', domain)],
		attributes: {
			title: { type: 'string', default: 'کوانتوم پدیا فارسی' },
			desc: { type: 'string', default: 'دانشنامه‌ای دقیق، کاربردی و خوش‌خوان برای یادگیری کوانتوم' }
		},
		edit: function (props) {
			var attributes = props.attributes;
			return el(Fragment, {},
				el(InspectorControls, { key: 'inspector' },
					el(TextControl, { label: __('تیتر اصلی', domain), value: attributes.title || '', onChange: updateAttribute(props, 'title') }),
					el(TextareaControl, { label: __('توضیح زیر تیتر', domain), value: attributes.desc || '', onChange: updateAttribute(props, 'desc') })
				),
				preview('◈', __('هیروی صفحه اصلی', domain), [attributes.title, attributes.desc])
			);
		},
		save: function () { return null; }
	});

	blocks.registerBlockType('qp/cats', {
		title: __('دسته‌بندی‌های صفحه اصلی', domain),
		description: __('دسته‌های دانشنامه با تعداد واقعی مقاله‌ها.', domain),
		icon: 'grid-view',
		category: 'widgets',
		keywords: [__('صفحه اصلی', domain), __('دسته', domain), __('موضوع', domain)],
		attributes: {
			title: { type: 'string', default: 'دسته‌بندی موضوعات' },
			num: { type: 'string', default: '۰۱' },
			note: { type: 'string', default: '' },
			count: { type: 'number', default: 7 },
			showSubs: { type: 'boolean', default: true }
		},
		edit: function (props) {
			var attributes = props.attributes;
			return el(Fragment, {},
				el(InspectorControls, { key: 'inspector' },
					el(TextControl, { label: __('شماره بخش', domain), value: attributes.num || '', onChange: updateAttribute(props, 'num') }),
					el(TextControl, { label: __('تیتر بخش', domain), value: attributes.title || '', onChange: updateAttribute(props, 'title') }),
					el(TextareaControl, { label: __('یادداشت بخش', domain), value: attributes.note || '', onChange: updateAttribute(props, 'note') }),
					el(RangeControl, { label: __('تعداد دسته‌ها', domain), value: attributes.count, min: 1, max: 20, onChange: updateAttribute(props, 'count') }),
					el(ToggleControl, { label: __('نمایش زیردسته‌ها', domain), checked: attributes.showSubs, onChange: updateAttribute(props, 'showSubs') })
				),
				preview('▦', __('دسته‌بندی موضوعات', domain), [attributes.title, __('نمایش %d دسته', domain).replace('%d', attributes.count)])
			);
		},
		save: function () { return null; }
	});

	blocks.registerBlockType('qp/posts', {
		title: __('مطالب صفحه اصلی', domain),
		description: __('فهرست خودکار مقاله‌های دانشنامه.', domain),
		icon: 'list-view',
		category: 'widgets',
		keywords: [__('صفحه اصلی', domain), __('مقاله', domain), __('آخرین', domain)],
		attributes: {
			title: { type: 'string', default: 'تازه‌ترین مقاله‌ها' },
			num: { type: 'string', default: '۰۲' },
			note: { type: 'string', default: '' },
			count: { type: 'number', default: 8 },
			orderby: { type: 'string', default: 'date' },
			exclude: { type: 'string', default: 'start,شروع' },
			showExcerpt: { type: 'boolean', default: false }
		},
		edit: function (props) {
			var attributes = props.attributes;
			return el(Fragment, {},
				el(InspectorControls, { key: 'inspector' },
					el(TextControl, { label: __('شماره بخش', domain), value: attributes.num || '', onChange: updateAttribute(props, 'num') }),
					el(TextControl, { label: __('تیتر بخش', domain), value: attributes.title || '', onChange: updateAttribute(props, 'title') }),
					el(TextareaControl, { label: __('یادداشت بخش', domain), value: attributes.note || '', onChange: updateAttribute(props, 'note') }),
					el(RangeControl, { label: __('تعداد مطالب', domain), value: attributes.count, min: 1, max: 12, onChange: updateAttribute(props, 'count') }),
					el(SelectControl, {
						label: __('ترتیب مطالب', domain),
						value: attributes.orderby,
						options: [
							{ label: __('تازه‌ترین‌ها', domain), value: 'date' },
							{ label: __('تازه‌ویرایش‌شده‌ها', domain), value: 'modified' },
							{ label: __('پربحث‌ترین‌ها', domain), value: 'comment_count' },
							{ label: __('تصادفی', domain), value: 'rand' },
							{ label: __('الفبایی', domain), value: 'title' }
						],
						onChange: updateAttribute(props, 'orderby')
					}),
					el(TextControl, { label: __('حذف از فهرست (نامک، عنوان یا شناسه)', domain), value: attributes.exclude || '', onChange: updateAttribute(props, 'exclude') }),
					el(ToggleControl, { label: __('نمایش خلاصه', domain), checked: attributes.showExcerpt, onChange: updateAttribute(props, 'showExcerpt') })
				),
				preview('☰', __('مطالب صفحه اصلی', domain), [attributes.title, __('نمایش %d مطلب', domain).replace('%d', attributes.count)])
			);
		},
		save: function () { return null; }
	});

	blocks.registerBlockType('qp/scientists', {
		title: __('دانشمندان صفحه اصلی', domain),
		description: __('فهرست خودکار دانشمندان کوانتوم.', domain),
		icon: 'groups',
		category: 'widgets',
		keywords: [__('صفحه اصلی', domain), __('دانشمند', domain)],
		attributes: {
			title: { type: 'string', default: 'چهره‌های کوانتوم' },
			num: { type: 'string', default: '۰۳' },
			note: { type: 'string', default: '' },
			count: { type: 'number', default: 6 },
			orderby: { type: 'string', default: 'date' }
		},
		edit: function (props) {
			var attributes = props.attributes;
			return el(Fragment, {},
				el(InspectorControls, { key: 'inspector' },
					el(TextControl, { label: __('شماره بخش', domain), value: attributes.num || '', onChange: updateAttribute(props, 'num') }),
					el(TextControl, { label: __('تیتر بخش', domain), value: attributes.title || '', onChange: updateAttribute(props, 'title') }),
					el(TextareaControl, { label: __('یادداشت بخش', domain), value: attributes.note || '', onChange: updateAttribute(props, 'note') }),
					el(RangeControl, { label: __('تعداد دانشمندان', domain), value: attributes.count, min: 1, max: 12, onChange: updateAttribute(props, 'count') }),
					el(SelectControl, {
						label: __('ترتیب', domain),
						value: attributes.orderby,
						options: [
							{ label: __('تازه‌ترین‌ها', domain), value: 'date' },
							{ label: __('تازه‌ویرایش‌شده‌ها', domain), value: 'modified' },
							{ label: __('تصادفی', domain), value: 'rand' },
							{ label: __('الفبایی', domain), value: 'title' }
						],
						onChange: updateAttribute(props, 'orderby')
					})
				),
				preview('●', __('دانشمندان صفحه اصلی', domain), [attributes.title, __('نمایش %d دانشمند', domain).replace('%d', attributes.count)])
			);
		},
		save: function () { return null; }
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n);
