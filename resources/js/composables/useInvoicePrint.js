import { computed } from 'vue';
import { useSettingsStore } from '@/stores/settings';

/**
 * Shared print plumbing for every invoice view in the app — sales,
 * purchases, returns, quotations. Each view used to ship its own
 * window.print() handler with a hard-coded A4 @page rule; this hook
 * centralises that so the same Settings.invoice_print_format value
 * drives everyone.
 *
 *   const { printFormat, isPosFormat, printInvoice } = useInvoicePrint();
 *
 * printInvoice() injects a fresh @page rule into the head right before
 * opening the print dialog. The rule is keyed on a single id so repeat
 * clicks replace rather than stack.
 */
export function useInvoicePrint() {
    const settingsStore = useSettingsStore();

    const printFormat = computed(
        () => settingsStore.settings?.invoice_print_format || 'a4'
    );

    const isPosFormat = computed(
        () => printFormat.value === 'pos80' || printFormat.value === 'pos58'
    );

    function printInvoice() {
        const prev = document.getElementById('pm-print-page-rule');
        if (prev) prev.remove();

        const style = document.createElement('style');
        style.id = 'pm-print-page-rule';

        if (printFormat.value === 'pos80') {
            style.textContent =
                '@media print { @page { size: 80mm auto; margin: 0; } body { margin: 0; } }';
        } else if (printFormat.value === 'pos58') {
            style.textContent =
                '@media print { @page { size: 58mm auto; margin: 0; } body { margin: 0; } }';
        } else {
            style.textContent =
                '@media print { @page { size: A4 portrait; margin: 12mm; } }';
        }

        document.head.appendChild(style);
        window.print();
    }

    return { printFormat, isPosFormat, printInvoice };
}
