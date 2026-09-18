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
    const lightModeClass = 'pdf-light'
    element.classList.add(lightModeClass)
    const lightModeStyle = document.createElement('style')
    lightModeStyle.dataset.reportPdfTheme = 'light'
    lightModeStyle.textContent = `
      .report-page.pdf-light { background: #ffffff !important; color: #102a43 !important; }
      .report-page.pdf-light .no-print { display: none !important; }
      .report-page.pdf-light .report-summary article,
      .report-page.pdf-light .report-card { background: #ffffff !important; color: #102a43 !important; border-color: #e4ecee !important; box-shadow: none !important; }
      .report-page.pdf-light h1, .report-page.pdf-light h2, .report-page.pdf-light h3,
      .report-page.pdf-light strong, .report-page.pdf-light .status-list strong { color: #102a43 !important; }
      .report-page.pdf-light p, .report-page.pdf-light small, .report-page.pdf-light span,
      .report-page.pdf-light td, .report-page.pdf-light th, .report-page.pdf-light .status-list div { color: #64748b !important; }
      .report-page.pdf-light .eyebrow, .report-page.pdf-light .status-pill { color: #006a78 !important; }
      .report-page.pdf-light .status-pill { background: #e7f8f9 !important; }
      .report-page.pdf-light th, .report-page.pdf-light td { border-color: #e4ecee !important; }
      .report-page.pdf-light .report-chart { background: #ffffff !important; }
    `
    document.head.appendChild(lightModeStyle)
    try {
      const { default: html2pdf } = await import('html2pdf.js')
      await new Promise(resolve => requestAnimationFrame(resolve))
      await html2pdf().set({
        margin: 10,
        filename: `${filename}.pdf`,
        image: { type: 'jpeg', quality: 0.96 },
        html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff' },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
      }).from(element).save()
    } finally {
      lightModeStyle.remove()
      element.classList.remove(lightModeClass)
      exportingPdf.value = false
    }
  }

  function print() {
    if (import.meta.client) window.print()
  }

  return { exportingPdf, exportingExcel, exportExcel, exportPdf, print }
}
