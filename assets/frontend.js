/**
 * WPCLP Frontend JS — handles AJAX unlock for both gates.
 *
 * Data source: wp_localize_script( 'wpclp-frontend', 'wpclpData', {
 *   ajaxUrl: admin_url('admin-ajax.php'),
 *   posts: {
 *     123: { gateType: 'password', nonce: '...', postId: 123 },
 *     456: { gateType: 'email',    nonce: '...', postId: 456 },
 *   }
 * })
 */
( function ( $ ) {
    'use strict';

    /**
     * Get the post ID from the form's wrapper element.
     *
     * @param {jQuery} $form
     * @returns {number|null}
     */
    function getPostId( $form ) {
        var postId = parseInt( $form.closest( '[data-post-id]' ).data( 'post-id' ), 10 );
        return isNaN( postId ) ? null : postId;
    }

    /**
     * Show an error message in the designated error div.
     *
     * @param {number} postId
     * @param {string} gateType  'password' | 'email'
     * @param {string} message
     */
    function showError( postId, gateType, message ) {
        var prefix  = ( gateType === 'email' ) ? 'wpclp-em-error-' : 'wpclp-pw-error-';
        var $errDiv = $( '#' + prefix + postId );
        $errDiv.text( message ).show();
    }

    /**
     * Clear the error div for a given post/gate.
     *
     * @param {number} postId
     * @param {string} gateType
     */
    function clearError( postId, gateType ) {
        var prefix  = ( gateType === 'email' ) ? 'wpclp-em-error-' : 'wpclp-pw-error-';
        var $errDiv = $( '#' + prefix + postId );
        $errDiv.text( '' ).hide();
    }

    /**
     * Generic AJAX submit handler shared by both gate types.
     *
     * @param {Event}  e
     * @param {string} gateType   'password' | 'email'
     * @param {string} fieldName  Name of the input field to collect
     */
    function handleSubmit( e, gateType, fieldName ) {
        e.preventDefault();

        var $form   = $( this );
        var postId  = getPostId( $form );

        if ( ! postId ) {
            return;
        }

        // Read nonce/postId from wpclpData — never from DOM.
        var postData = wpclpData.posts[ postId ];
        if ( ! postData ) {
            return;
        }

        var fieldValue = $form.find( '[name="' + fieldName + '"]' ).val();
        var $submitBtn = $form.find( '[type="submit"]' );
        var action     = 'wpclp_unlock_' + gateType;

        clearError( postId, gateType );
        $submitBtn.prop( 'disabled', true );

        var requestData = {
            action:   action,
            post_id:  postData.postId,
            nonce:    postData.nonce
        };
        requestData[ fieldName ] = fieldValue;

        $.ajax( {
            url:    wpclpData.ajaxUrl,
            type:   'POST',
            data:   requestData,
            success: function ( response ) {
                if ( response && response.success ) {
                    // Reload so the server-side cookie check reveals the content.
                    window.location.reload();
                } else {
                    var msg = ( response && response.data && response.data.message )
                        ? response.data.message
                        : wpclpData.i18n && wpclpData.i18n.error
                            ? wpclpData.i18n.error
                            : 'An error occurred. Please try again.';
                    showError( postId, gateType, msg );
                    $submitBtn.prop( 'disabled', false );
                }
            },
            error: function () {
                var msg = ( wpclpData.i18n && wpclpData.i18n.networkError )
                    ? wpclpData.i18n.networkError
                    : 'Network error. Please try again.';
                showError( postId, gateType, msg );
                $submitBtn.prop( 'disabled', false );
            }
        } );
    }

    // ── Password gate ──────────────────────────────────────────────────────────
    $( document ).on( 'submit', '.wpclp-lock-password form', function ( e ) {
        handleSubmit.call( this, e, 'password', 'wpclp_password' );
    } );

    // ── Email gate ─────────────────────────────────────────────────────────────
    $( document ).on( 'submit', '.wpclp-lock-email form', function ( e ) {
        handleSubmit.call( this, e, 'email', 'wpclp_email' );
    } );

}( jQuery ) );
