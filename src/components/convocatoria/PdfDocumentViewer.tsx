import { useEffect, useRef, useState } from 'react';
import { Document, Page, pdfjs } from 'react-pdf';
import 'react-pdf/dist/Page/AnnotationLayer.css';
import 'react-pdf/dist/Page/TextLayer.css';

pdfjs.GlobalWorkerOptions.workerSrc = new URL(
  'pdfjs-dist/build/pdf.worker.min.mjs',
  import.meta.url,
).toString();

interface PdfDocumentViewerProps {
  url: string;
}

const PdfDocumentViewer = ({ url }: PdfDocumentViewerProps) => {
  const containerRef = useRef<HTMLDivElement>(null);
  const [pageCount, setPageCount] = useState(0);
  const [pageWidth, setPageWidth] = useState(320);

  useEffect(() => {
    const container = containerRef.current;
    if (!container) return;

    const updateWidth = () => {
      setPageWidth(Math.max(280, Math.floor(container.clientWidth - 16)));
    };

    updateWidth();
    const observer = new ResizeObserver(updateWidth);
    observer.observe(container);
    return () => observer.disconnect();
  }, []);

  return (
    <div
      ref={containerRef}
      className="w-full overflow-x-hidden bg-foreground/90 px-2 py-3 sm:px-4"
    >
      <Document
        file={url}
        loading={<p className="py-12 text-center text-muted-foreground">Cargando documento…</p>}
        error={<p className="py-12 text-center text-destructive">No fue posible mostrar el PDF.</p>}
        onLoadSuccess={({ numPages }) => setPageCount(numPages)}
      >
        <div className="flex flex-col items-center gap-3">
          {Array.from({ length: pageCount }, (_, index) => (
            <Page
              key={`page-${index + 1}`}
              pageNumber={index + 1}
              width={pageWidth}
              renderAnnotationLayer={false}
              renderTextLayer={false}
              loading={<div className="h-96 w-full max-w-3xl animate-pulse bg-muted" />}
              className="overflow-hidden rounded-sm border border-border bg-card shadow-sm"
            />
          ))}
        </div>
      </Document>
    </div>
  );
};

export default PdfDocumentViewer;