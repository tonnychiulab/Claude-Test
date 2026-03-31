/**
 * WPCLP Admin JS
 * Gate-type toggle: show/hide password field based on selected gate_type.
 * Email list toggle: expand/collapse per-post email lists.
 * No jQuery dependency.
 */
( function () {
    'use strict';

    /**
     * Meta box: show/hide the password row based on the gate-type select.
     */
    function initGateTypeToggle() {
        var gateTypeSelect  = document.getElementById( 'wpclp-gate-type' );
        var passwordRow     = document.getElementById( 'wpclp-password-row' );

        if ( ! gateTypeSelect || ! passwordRow ) {
            return;
        }

        function togglePasswordRow() {
            if ( gateTypeSelect.value === 'password' ) {
                passwordRow.style.display = '';
            } else {
                passwordRow.style.display = 'none';
            }
        }

        // Set initial state
        togglePasswordRow();

        gateTypeSelect.addEventListener( 'change', togglePasswordRow );
    }

    /**
     * Dashboard: toggle per-post email lists.
     */
    function initEmailListToggles() {
        var toggleButtons = document.querySelectorAll( '.wpclp-toggle-emails' );

        if ( ! toggleButtons.length ) {
            return;
        }

        toggleButtons.forEach( function ( btn ) {
            btn.addEventListener( 'click', function () {
                var postId   = btn.getAttribute( 'data-post-id' );
                var listEl   = document.getElementById( 'wpclp-emails-' + postId );
                var expanded = btn.getAttribute( 'aria-expanded' ) === 'true';

                if ( ! listEl ) {
                    return;
                }

                if ( expanded ) {
                    listEl.hidden = true;
                    btn.setAttribute( 'aria-expanded', 'false' );
                    btn.textContent = wpclpAdmin.showEmails || 'Show emails';
                } else {
                    listEl.hidden = false;
                    btn.setAttribute( 'aria-expanded', 'true' );
                    btn.textContent = wpclpAdmin.hideEmails || 'Hide emails';
                }
            } );
        } );
    }

    document.addEventListener( 'DOMContentLoaded', function () {
        initGateTypeToggle();
        initEmailListToggles();
    } );

}() );
