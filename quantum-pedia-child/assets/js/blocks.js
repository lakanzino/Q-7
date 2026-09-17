/* ══════════════════════════════════════════════════════════════
   بلوک‌های صفحه اصلی کوانتوم‌پدیا (v1.5.0) — بدون بیلد، JS ساده
   سه بلوک داینامیک: هیرو، دسته‌ها، مطالب. رندر سمت سرور (PHP).
   ══════════════════════════════════════════════════════════════ */
(function (blocks, element, blockEditor, components, i18n) {
	if (!blocks || !element || !blockEditor || !components) {
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

	/* قاب پیش‌نمایش داخل ویرایشگر */
	function ph(icon, title, lines) {
		var kids = [
			el('div', { className: 'qp-block-ph__icon', key: 'i' }, icon),
			el('strong', { className: 'qp-block-ph__title', key: 't' }, title)
		];
		(lines || []).forEach(function (ln, idx) {
			if (ln) {
				kids.push(el('p', { className: 'qp-block-ph__line', key: 'l' + idx }, ln));
			}
		});
		kids.push(el('p', { className: 'qp-block-ph__hint', key: 'h' }, 'تنظیمات این بلوک در ستون کناری است'));
		return el('div', { className: 'qp-block-ph' }, kids);
	}

	function onAttr(props, key) {
		return function (val) {
			var upd = {};
			upd[key] = val;
			props.setAttributes(upd);
		};
	}

	/* ─── ۱) هیرو + شمارشگرهای زنده ─── */
	blocks.registerBlockType('qp/hero', {
		title: 'هیروی صفحه اصلی',
		description: 'تیتر، توضیح و شمارشگرهای زنده دانشنامه.',
		icon: 'cover-image',
		category: 'common',
		keywords: ['صفحه اصلی', 'هیرو', 'آمار'],
		attributes: {
			badge: { type: 'string', default: 'دانشنامهٔ فارسی فیزیک کوانتوم' },
			title: { type: 'string', default: 'کوانتوم را از پایه، دقیق و روان یاد بگیرید' },
			desc: { type: 'string', default: 'از مبانی نظری تا فناوری‌های واقعی — با مقاله‌های کوتاه، دسته‌بندی روشن و مسیر مطالعهٔ قابل‌فهم.' },
			showStats: { type: 'boolean', default: true }
		},
		edit: function (props) {
			var a = props.attributes;
			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{ key: 'ins' },
					el(TextControl, { label: 'نشان (بالای تیتر)', value: a.badge, onChange: onAttr(props, 'badge') }),
					el(TextControl, { label: 'تیتر اصلی', value: a.title, onChange: onAttr(props, 'title') }),
					el(TextareaControl, { label: 'توضیح زیر تیتر', value: a.desc, onChange: onAttr(props, 'desc') }),
					el(ToggleControl, { label: 'نمایش شمارشگرها', checked: a.showStats, onChange: onAttr(props, 'showStats') })
				),
				ph('◈', 'هیروی صفحه اصلی', [a.title, a.showStats ? 'همراه شمارشگرهای زنده' : 'بدون شمارشگر'])
			);
		},
		save: function () {
			return null;
		}
	});

	/* ─── ۲) دسته‌بندی‌ها (زنده) ─── */
	blocks.registerBlockType('qp/cats', {
		title: 'دسته‌بندی‌های صفحه اصلی',
		description: 'گرید دسته‌های دانشنامه با تعداد واقعی مقاله‌ها.',
		icon: 'grid-view',
		category: 'common',
		keywords: ['صفحه اصلی', 'دسته', 'موضوع'],
		attributes: {
			eyebrow: { type: 'string', default: 'ساختار دانشنامه' },
			title: { type: 'string', default: 'دسته‌بندی موضوعات' },
			desc: { type: 'string', default: 'هفت مسیر اصلی برای خواندن موضوعی مقاله‌ها.' },
			count: { type: 'number', default: 7 }
		},
		edit: function (props) {
			var a = props.attributes;
			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{ key: 'ins' },
					el(TextControl, { label: 'ابرتیتر', value: a.eyebrow, onChange: onAttr(props, 'eyebrow') }),
					el(TextControl, { label: 'تیتر بخش', value: a.title, onChange: onAttr(props, 'title') }),
					el(TextareaControl, { label: 'توضیح بخش', value: a.desc, onChange: onAttr(props, 'desc') }),
					el(RangeControl, { label: 'تعداد دسته‌ها', value: a.count, min: 1, max: 20, onChange: onAttr(props, 'count') })
				),
				ph('▦', 'دسته‌بندی موضوعات', [a.title, 'نمایش ' + a.count + ' دسته'])
			);
		},
		save: function () {
			return null;
		}
	});

	/* ─── ۳) مطالب (زنده) ─── */
	blocks.registerBlockType('qp/posts', {
		title: 'مطالب صفحه اصلی',
		description: 'فهرست خودکار مقاله‌ها: تازه‌ترین، پربحث‌ترین یا تصادفی.',
		icon: 'list-view',
		category: 'common',
		keywords: ['صفحه اصلی', 'مقاله', 'آخرین'],
		attributes: {
			title: { type: 'string', default: 'آخرین مقاله‌ها' },
			count: { type: 'number', default: 6 },
			orderby: { type: 'string', default: 'date' },
			exclude: { type: 'string', default: 'start,شروع' }
		},
		edit: function (props) {
			var a = props.attributes;
			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{ key: 'ins' },
					el(TextControl, { label: 'تیتر بخش', value: a.title, onChange: onAttr(props, 'title') }),
					el(RangeControl, { label: 'تعداد مطالب', value: a.count, min: 1, max: 12, onChange: onAttr(props, 'count') }),
					el(SelectControl, {
						label: 'ترتیب مطالب',
						value: a.orderby,
						options: [
							{ label: 'تازه‌ترین‌ها', value: 'date' },
							{ label: 'تازه‌ویرایش‌شده‌ها', value: 'modified' },
							{ label: 'پربحث‌ترین‌ها (جذاب‌ترین)', value: 'comment_count' },
							{ label: 'تصادفی', value: 'rand' },
							{ label: 'الفبا', value: 'title' }
						],
						onChange: onAttr(props, 'orderby')
					}),
					el(TextControl, { label: 'حذف از لیست (نامک یا تیتر، با ویرگول)', value: a.exclude, onChange: onAttr(props, 'exclude'), help: 'مقاله‌هایی که نباید در لیست بیایند.' })
				),
				ph('☰', 'مطالب صفحه اصلی', [a.title, 'نمایش ' + a.count + ' مطلب'])
			);
		},
		save: function () {
			return null;
		}
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n);
