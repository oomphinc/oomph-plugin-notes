(function($) {
	$('.plugin-notes-container.editable, .plugin-notes-edit').on('click', function() {
		var $container = $(this).closest('.plugin-notes-container')
		  , $textarea = $('<textarea class="widefat">')
		  , $saveButton = $('<button class="button-primary">')
		  , $cancelButton = $('<button class="button-secondary">')
		;

		if($container.hasClass('editing')) {
			return;
		}

		$container.addClass('editing');

		$saveButton.text(OPN.text.save);
		$cancelButton.text(OPN.text.cancel);

		$container.data('plugin-markup', $container.html());

		$textarea.val($container.data('plugin-notes'));

		function adjust() {
			$textarea.height(1);
			$textarea.height($textarea[0].scrollHeight);
		}

		// Make sure the text area is the same number of lines contained within
		$textarea.on('keyup', adjust);

		$container.html('');
		$container.append($textarea);
		$container.append($saveButton);
		$container.append(' ');
		$container.append($cancelButton);

		adjust();

		$textarea.focus();

		function commit(body) {
			var $p = $('<p>'), html;

			if(typeof body === 'undefined') {
				html = $container.data('plugin-markup')
			}
			else {
				html = body.markup;
			}

			$container.html(html);
			$container.removeClass('editing');
		}

		$saveButton.on('click', function(ev) {
			ev.stopPropagation();
			ev.preventDefault();

			$container.data('plugin-notes', $textarea.val());
			wp.ajax.post('oomph-plugin-notes-save', {
				plugin: $container.parents('tr').attr('id'),
				notes: $textarea.val(),
				nonce: OPN.nonce
			}).done(commit).fail(function() {
				$container.addClass('plugin-notes-error');
			});


		});

		$cancelButton.on('click', function(ev) {
			ev.stopPropagation();
			ev.preventDefault();
			commit();
		});
	});
})(jQuery);
