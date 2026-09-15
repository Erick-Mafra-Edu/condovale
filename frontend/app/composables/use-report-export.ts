export function useReportExport() {
  const exportingPdf = ref(false)
  const exportingExcel = ref(false)

  async function exportExcel(rows: Array<Record<string, string | number>>, filename = 'relatorio-condovale') {
    if (!import.meta.client) return
    exportingExcel.value = true
    try {
      const { utils, writeFile } = await import('xlsx')
      const sheet = utils.json_to_sheet(rows)
      const workbook = utils.book_new()
      utils.book_append_sheet(workbook, sheet, 'Relatório')
      writeFile(workbook, `${filename}.xlsx`)
    } finally { exportingExcel.value = false }
  }

  async function exportPdf(element: HTMLElement | null, filename = 'relatorio-condovale') {
    if (!import.meta.client || !element) return
    exportingPdf.value = true
    try {
      const { default: html2pdf } = await import('html2pdf.js')
      await html2pdf().set({
        margin: 10,
        filename: `${filename}.pdf`,
        image: { type: 'jpeg', quality: 0.96 },
        html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff' },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
      }).from(element).save()
    } finally { exportingPdf.value = false }
  }

  function print() {
    if (import.meta.client) window.print()
  }

  return { exportingPdf, exportingExcel, exportExcel, exportPdf, print }
}
