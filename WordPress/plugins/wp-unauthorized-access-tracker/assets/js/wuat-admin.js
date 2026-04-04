/**
 * WP Unauthorized Access Tracker — Admin JS
 *
 * Handles:
 *  - CSV export via AJAX
 *  - Bulk-delete confirmation
 */
( function ( $ ) {
    'use strict';

    // ── CSV Export ────────────────────────────────────────────────────────────

    $( '#wuat-export-csv' ).on( 'click', function () {
        var $btn    = $( this );
        var $status = $( '#wuat-export-status' );

        $btn.prop( 'disabled', true );
        $status.text( wuat_ajax.export_text );

        $.post(
            wuat_ajax.ajax_url,
            {
                action:     'wuat_export_csv',
                nonce:      wuat_ajax.nonce,
                event_type: wuat_ajax.filters.event_type,
                user_login: wuat_ajax.filters.user_login,
                ip_address: wuat_ajax.filters.ip_address,
                date_from:  wuat_ajax.filters.date_from,
                date_to:    wuat_ajax.filters.date_to
            },
            function ( response ) {
                $btn.prop( 'disabled', false );

                if ( response.success && response.data && response.data.csv ) {
                    downloadCsv( response.data.csv );
                    $status.text( wuat_ajax.done_text );
                } else {
                    $status.text( wuat_ajax.error_text );
                }
            }
        ).fail( function () {
            $btn.prop( 'disabled', false );
            $status.text( wuat_ajax.error_text );
        } );
    } );

    /**
     * Trigger a browser download for CSV content.
     *
     * @param {string} csvContent CSV string.
     */
    function downloadCsv( csvContent ) {
        var blob = new Blob( [ '\uFEFF' + csvContent ], { type: 'text/csv;charset=utf-8;' } );
        var url  = URL.createObjectURL( blob );
        var ts   = new Date().toISOString().slice( 0, 10 );
        var link = document.createElement( 'a' );

        link.setAttribute( 'href', url );
        link.setAttribute( 'download', 'wuat-audit-log-' + ts + '.csv' );
        link.style.display = 'none';
        document.body.appendChild( link );
        link.click();
        document.body.removeChild( link );
        setTimeout( function () { URL.revokeObjectURL( url ); }, 1000 );
    }

    // ── Bulk delete (AJAX — nonce + permission enforced server-side) ─────────

    $( 'form' ).on( 'submit', function ( e ) {
        var action = $( '#bulk-action-selector-top' ).val() ||
                     $( '#bulk-action-selector-bottom' ).val();

        if ( 'delete' !== action ) {
            return;
        }

        var $checked = $( 'input[name="log_ids[]"]:checked' );
        if ( 0 === $checked.length ) {
            return;
        }

        // Always prevent default — deletion goes through AJAX, not form POST.
        e.preventDefault();

        var confirmMsg = wuat_ajax.confirm_text.replace( '%d', $checked.length );
        if ( ! window.confirm( confirmMsg ) ) {
            return;
        }

        var ids = $checked.map( function () { return $( this ).val(); } ).get();

        $.post(
            wuat_ajax.ajax_url,
            {
                action: 'wuat_delete_logs',
                nonce:  wuat_ajax.nonce,
                ids:    ids
            },
            function ( response ) {
                if ( response.success ) {
                    window.location.reload();
                } else {
                    var msg = response.data && response.data.message
                        ? response.data.message
                        : wuat_ajax.error_text;
                    window.alert( msg );
                }
            }
        ).fail( function () {
            window.alert( wuat_ajax.error_text );
        } );
    } );

}( jQuery ) );
