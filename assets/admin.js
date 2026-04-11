/**
 * Mega Kadence Bridge — Admin Settings Page Scripts
 *
 * Handles the "Copy as .env" button click.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var buttons = document.querySelectorAll('.mkb-copy-button');

		buttons.forEach(function (button) {
			button.addEventListener('click', function () {
				var targetId = button.getAttribute('data-target');
				var target = document.getElementById(targetId);
				if (!target) {
					return;
				}

				var text = target.textContent || '';
				var feedback = button.parentNode.querySelector('.mkb-copy-feedback');

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard
						.writeText(text)
						.then(function () {
							showFeedback(feedback, 'Copied!');
						})
						.catch(function () {
							fallbackCopy(target, feedback);
						});
				} else {
					fallbackCopy(target, feedback);
				}
			});
		});
	});

	function fallbackCopy(target, feedback) {
		var range = document.createRange();
		range.selectNode(target);
		var selection = window.getSelection();
		selection.removeAllRanges();
		selection.addRange(range);
		try {
			document.execCommand('copy');
			showFeedback(feedback, 'Copied!');
		} catch (err) {
			showFeedback(feedback, 'Press Ctrl+C to copy', true);
		}
		selection.removeAllRanges();
	}

	function showFeedback(feedback, message, isError) {
		if (!feedback) return;
		feedback.textContent = message;
		feedback.classList.add('mkb-visible');
		if (isError) {
			feedback.style.color = '#cc1818';
		}
		setTimeout(function () {
			feedback.classList.remove('mkb-visible');
		}, 2000);
	}
})();
