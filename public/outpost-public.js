/* OutPost - Public JS
   Handles subscribe form and email lookup AJAX.
   -------------------------------------------- */
(function ($) {
	'use strict';

	// -------------------------------------------------------------------------
	// Subscribe form
	// -------------------------------------------------------------------------
	$(document).on('submit', '.outpost-subscribe__form', function (e) {
		e.preventDefault();

		var $form    = $(this);
		var $wrap    = $form.closest('.outpost-subscribe');
		var $msgs    = $wrap.find('.outpost-subscribe__messages');
		var $btn     = $form.find('button[type="submit"]');
		var origText = $btn.text();

		var hashtagId = $form.data('hashtag-id');
		var email     = $form.find('input[name="outpost_email"]').val().trim();
		var name      = $form.find('input[name="outpost_name"]').val().trim();

		// Basic client-side validation
		if ( ! email ) {
			setMessage( $msgs, 'error', outpostData.strings ? outpostData.strings.emailRequired : 'Please enter your email address.' );
			$form.find('input[name="outpost_email"]').focus();
			return;
		}

		$btn.prop('disabled', true).text('...');
		setMessage( $msgs, 'status', 'Subscribing…' );

		$.ajax({
			url:  outpostData.ajaxUrl,
			type: 'POST',
			data: {
				action:       'outpost_subscribe',
				outpost_nonce:    outpostData.nonce,
				hashtag_id:   hashtagId,
				email:        email,
				name:         name,
			},
			success: function (response) {
				if ( response.success ) {
					setMessage( $msgs, 'success', response.data.message );
					$wrap.addClass( 'outpost-subscribe--confirmed' );
					$msgs.focus();
					$form[0].reset();
				} else {
					setMessage( $msgs, 'error', response.data.message );
				}
			},
			error: function () {
				setMessage( $msgs, 'error', 'Something went wrong. Please try again.' );
			},
			complete: function () {
				if ( ! $wrap.hasClass( 'outpost-subscribe--confirmed' ) ) {
					$btn.prop('disabled', false).text(origText);
				}
			}
		});
	});

	// -------------------------------------------------------------------------
	// Email lookup form
	// -------------------------------------------------------------------------
	$(document).on('submit', '.outpost-lookup__form', function (e) {
		e.preventDefault();

		var $form    = $(this);
		var $msgs    = $form.siblings('.outpost-lookup__messages');
		var $results = $form.siblings('.outpost-lookup__results');
		var $btn     = $form.find('button[type="submit"]');
		var origText = $btn.text();
		var email    = $form.find('input[name="outpost_lookup_email"]').val().trim();
		var nonce    = $form.find('input[name="outpost_lookup_nonce"]').val();

		if ( ! email ) {
			setMessage( $msgs, 'error', 'Please enter your email address.' );
			$form.find('input[name="outpost_lookup_email"]').focus();
			return;
		}

		$btn.prop('disabled', true).text('...');
		setMessage( $msgs, 'status', 'Looking up your subscriptions…' );
		$results.html('');

		$.ajax({
			url:  outpostData.ajaxUrl,
			type: 'POST',
			data: {
				action:    'outpost_lookup',
				outpost_nonce: nonce,
				email:     email,
			},
			success: function (response) {
				if ( response.success ) {
					$results.html( response.data.html );
				} else {
					setMessage( $msgs, 'error', response.data.message );
				}
			},
			error: function () {
				setMessage( $msgs, 'error', 'Something went wrong. Please try again.' );
			},
			complete: function () {
				$btn.prop('disabled', false).text(origText);
			}
		});
	});

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------
	function setMessage( $el, type, text ) {
		// Drive announcements via the container's aria-live rather than a nested
		// role="alert" (which conflicts with the outer live region on NVDA/JAWS).
		$el.attr( 'aria-live', type === 'error' ? 'assertive' : 'polite' );
		$el.html( '<div class="outpost-message--' + type + '">' + escHtml( text ) + '</div>' );
	}

	function escHtml( str ) {
		return $('<div>').text(str).html();
	}

})(jQuery);
