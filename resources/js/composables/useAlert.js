import Swal from 'sweetalert2';

// ── Toast mixin (top-right, auto-dismiss) ─────────────────────────────────
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
    customClass: {
        popup:          'swal-pos-toast',
        timerProgressBar: 'swal-pos-progress',
    },
    showClass:  { popup: 'swal-pos-toast-show' },
    hideClass:  { popup: 'swal-pos-toast-hide' },
});

export function useAlert() {

    /**
     * Show a toast notification.
     * @param {'success'|'error'|'warning'|'info'} icon
     * @param {string} title
     */
    function toast(icon, title) {
        Toast.fire({ icon, title });
    }

    /**
     * Show a confirmation dialog.
     * Resolves to true if the user clicks Confirm, false if they cancel.
     *
     * @param {{title:string, text?:string, confirmText?:string, danger?:boolean}} options
     */
    async function confirm({ title, text = '', confirmText = 'Confirm', cancelText = 'Cancel', danger = false }) {
        const result = await Swal.fire({
            title,
            text,
            icon: danger ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            reverseButtons: true,
            focusCancel: true,
            buttonsStyling: false,
            customClass: {
                popup:         'swal-pos-popup',
                title:         'swal-pos-title',
                htmlContainer: 'swal-pos-text',
                confirmButton: danger ? 'swal-pos-btn-danger' : 'swal-pos-btn-confirm',
                cancelButton:  'swal-pos-btn-cancel',
                actions:       'swal-pos-actions',
                icon:          danger ? 'swal-pos-icon-danger' : 'swal-pos-icon-question',
            },
        });
        return result.isConfirmed;
    }

    return { toast, confirm };
}
