/**
 * AdminUploads
 * -----------------------------------------------------------------------
 * Admin tab to upload, list, and delete media files served from the
 * IONOS server via `/api/uploads.php`. One sub-tab per logical section:
 *
 *   - Eventos       (images for the Eventos page poster grid)
 *   - Avisos        (images for the Avisos page poster grid)
 *   - Bases  (images for the Bases gallery + the downloadable
 *                    convocatoria PDF — first PDF in this section is what
 *                    the public page links to, regardless of filename)
 *   - Reglas        (the downloadable Reglas y CC PDF — first PDF in this
 *                    section is what the public page links to)
 *
 * The component is purely presentational/admin — it never alters
 * tournament data and only talks to the uploads endpoint. Data fetching
 * and mutations come from `useUploads.ts`. Files are scoped to the
 * current Host header by the backend, so each domain manages its own set.
 */

import { useRef, useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
  Upload,
  Trash2,
  FileImage,
  FileText,
  Loader2,
  CalendarDays,
  Bell,
  UtensilsCrossed,
  ScrollText,
  BookOpen,
  HardDrive,
  Info,
  Trophy,
  Flag,
  Hotel,
} from 'lucide-react';
import {
  useUploadsList,
  useUploadFiles,
  useDeleteFile,
  type UploadSection,
  type UploadedFile,
} from '@/hooks/useUploads';
import { useToast } from '@/hooks/use-toast';
import { cn } from '@/lib/utils';

// ============= Section metadata =============

/** Display configuration for each upload section tab. */
interface SectionMeta {
  /** Backend section identifier */
  id: UploadSection;
  /** Tab label (visible) */
  label: string;
  /** Lucide icon component */
  Icon: React.ComponentType<{ className?: string }>;
  /** Short description shown above the uploader */
  description: string;
  /** `accept` attribute for the file input */
  accept: string;
  /**
   * Render hint for the listing UI:
   *   - 'image': only images, renders a thumbnail grid.
   *   - 'pdf'  : only PDFs, renders a row list.
   *   - 'mixed': both can coexist; each file is rendered per its own
   *              extension (PDFs as rows, images as thumbnails).
   */
  kind: 'image' | 'pdf' | 'mixed';
}

const SECTIONS: SectionMeta[] = [
  {
    id: 'eventos',
    label: 'Eventos',
    Icon: CalendarDays,
    description:
      'Imágenes que aparecen en el grid de pósters de la página Eventos. Súbelas con prefijos numéricos (01-, 02-...) para controlar el orden.',
    accept: 'image/webp,image/jpeg,image/png,image/gif',
    kind: 'image',
  },
  {
    id: 'avisos',
    label: 'Avisos',
    Icon: Bell,
    description:
      'Imágenes para el grid de Avisos (clima, costos, comunicados). Mismo formato y reglas de orden que Eventos.',
    accept: 'image/webp,image/jpeg,image/png,image/gif',
    kind: 'image',
  },
  {
    id: 'menus',
    label: 'Menús',
    Icon: UtensilsCrossed,
    description:
      'Imágenes para el grid de la página Menús (alimentos, bebidas, paquetes). Súbelas con prefijos numéricos (01-, 02-...) para controlar el orden.',
    accept: 'image/webp,image/jpeg,image/png,image/gif',
    kind: 'image',
  },
  {
    id: 'premios',
    label: 'Premios',
    Icon: Trophy,
    description:
      'Imágenes que aparecen en el grid de pósters de la página Premios. Súbelas con prefijos numéricos (01-, 02-...) para controlar el orden.',
    accept: 'image/webp,image/jpeg,image/png,image/gif',
    kind: 'image',
  },
  {
    id: 'hoteles',
    label: 'Hoteles',
    Icon: Hotel,
    description:
      'Imágenes que aparecen en el grid de pósters de la página Hoteles. Súbelas con prefijos numéricos (01-, 02-...) para controlar el orden.',
    accept: 'image/webp,image/jpeg,image/png,image/gif',
    kind: 'image',
  },
  {
    id: 'convocatoria',
    label: 'Bases',
    Icon: ScrollText,
    description:
      'Dos botones independientes: "Subir imágenes" para una galería complementaria (uso futuro) y "Subir PDF" para el documento oficial. El primer PDF subido es el que aparece en el botón "Ver en PDF" de la página pública — el nombre del archivo no importa.',
    accept: 'image/webp,image/jpeg,image/png,image/gif,application/pdf',
    kind: 'mixed',
  },
  {
    id: 'reglas',
    label: 'Reglas',
    Icon: BookOpen,
    description:
      'PDF de Reglas y Términos de Competencia. El primer PDF que subas se usará como "Ver Reglas y T. de Competencia" en la página pública (no importa el nombre del archivo).',
    accept: 'application/pdf',
    kind: 'pdf',
  },
  {
    id: 'skinrules',
    label: 'Skin Rules',
    Icon: BookOpen,
    description:
      'PDF de Reglas del Skin Game. El primer PDF que subas se usará como "Ver Reglas del Skin Game" en la página pública /skinrules (no importa el nombre del archivo).',
    accept: 'application/pdf',
    kind: 'pdf',
  },
  {
    id: 'banderas',
    label: 'Banderas',
    Icon: Flag,
    description:
      'Pin sheet (posición de banderas) del día. Sube el PDF oficial o imágenes/escaneos como respaldo. La página /banderas muestra primero la visualización custom de los 18 greens y debajo los archivos subidos como galería descargable.',
    accept: 'image/webp,image/jpeg,image/png,image/gif,application/pdf',
    kind: 'mixed',
  },
];

/** Shared admin password (mirrors site_config.php usage). */
const ADMIN_PASSWORD = 'admin2025';

/** Format byte count as human-readable size. */
const formatBytes = (bytes: number): string => {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

/**
 * Decide whether a single file is a PDF based on its extension.
 * Used to split mixed sections (Bases) into PDF rows + image grid.
 */
const isPdfFile = (name: string): boolean => /\.pdf$/i.test(name);

// ============= Per-section uploader panel =============

interface SectionPanelProps {
  meta: SectionMeta;
}

/**
 * SectionPanel
 * Single section card: file picker + grid/list of existing files
 * with delete buttons.
 */
const SectionPanel = ({ meta }: SectionPanelProps) => {
  const { toast } = useToast();
  // Separate refs for the two upload modes so a `mixed` section (Bases)
  // can offer two distinct buttons — one strictly for images, one strictly
  // for PDFs — each with its own `accept` attribute. Single-mode sections
  // only use `imageInputRef` OR `pdfInputRef`.
  const imageInputRef = useRef<HTMLInputElement>(null);
  const pdfInputRef = useRef<HTMLInputElement>(null);
  const [confirmDelete, setConfirmDelete] = useState<UploadedFile | null>(null);

  const { data, isLoading, error, refetch } = useUploadsList(meta.id);
  const uploadMutation = useUploadFiles(meta.id);
  const deleteMutation = useDeleteFile(meta.id);

  /**
   * Handle file selection — uploads immediately.
   * `inputEl` is the originating <input>; we reset its value on completion
   * so re-selecting the same filename re-fires the change event.
   */
  const handleFiles = (filesList: FileList | null, inputEl: HTMLInputElement | null) => {
    if (!filesList || filesList.length === 0) return;
    const files = Array.from(filesList);
    uploadMutation.mutate(
      { files, password: ADMIN_PASSWORD },
      {
        onSuccess: (response) => {
          const okCount = response.saved.length;
          const errCount = response.errors.length;
          if (okCount > 0) {
            toast({
              title: `${okCount} archivo${okCount === 1 ? '' : 's'} subido${okCount === 1 ? '' : 's'}`,
              description: errCount > 0 ? `${errCount} con error — revisa el detalle.` : 'Listo.',
            });
          }
          if (errCount > 0) {
            toast({
              title: 'Algunos archivos fallaron',
              description: response.errors.map((e) => `${e.name}: ${e.error}`).join(' · '),
              variant: 'destructive',
            });
          }
          // Reset native input so re-selecting the same file re-triggers change.
          if (inputEl) inputEl.value = '';
        },
        onError: (err) => {
          toast({
            title: 'Error al subir',
            description: err.message,
            variant: 'destructive',
          });
        },
      }
    );
  };

  /** Confirm + execute deletion of a single file. */
  const handleConfirmDelete = () => {
    if (!confirmDelete) return;
    const target = confirmDelete;
    setConfirmDelete(null);
    deleteMutation.mutate(
      { name: target.name, password: ADMIN_PASSWORD },
      {
        onSuccess: () => {
          toast({
            title: 'Archivo eliminado',
            description: target.name,
          });
        },
        onError: (err) => {
          toast({
            title: 'Error al eliminar',
            description: err.message,
            variant: 'destructive',
          });
        },
      }
    );
  };

  const files = data?.files ?? [];

  // Determine which buttons to render. `mixed` shows BOTH; single-mode
  // sections show only the relevant one. This keeps the UI honest about
  // what each button does (no ambiguous "subir cualquier cosa").
  const showImageButton = meta.kind === 'image' || meta.kind === 'mixed';
  const showPdfButton = meta.kind === 'pdf' || meta.kind === 'mixed';

  return (
    <div className="space-y-6">
      {/* ---------- Section header + uploader ---------- */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <meta.Icon className="h-5 w-5 text-primary" />
            {meta.label}
          </CardTitle>
          <CardDescription>{meta.description}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {/*
            Hidden inputs — one per accepted media type. The visible buttons
            below trigger the matching input via ref. Two inputs (instead of
            a single multi-accept one) let the OS file picker pre-filter to
            the exact type the admin asked for, and let us label each button
            unambiguously.
          */}
          {showImageButton && (
            <input
              ref={imageInputRef}
              type="file"
              multiple
              accept="image/webp,image/jpeg,image/png,image/gif"
              className="hidden"
              onChange={(e) => handleFiles(e.target.files, imageInputRef.current)}
            />
          )}
          {showPdfButton && (
            <input
              ref={pdfInputRef}
              type="file"
              multiple
              accept="application/pdf"
              className="hidden"
              onChange={(e) => handleFiles(e.target.files, pdfInputRef.current)}
            />
          )}

          <div className="flex flex-wrap items-center gap-3">
            {showImageButton && (
              <Button
                type="button"
                onClick={() => imageInputRef.current?.click()}
                disabled={uploadMutation.isPending}
                className="gap-2"
              >
                {uploadMutation.isPending ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <FileImage className="h-4 w-4" />
                )}
                Subir imágenes
              </Button>
            )}
            {showPdfButton && (
              <Button
                type="button"
                // Use a distinct visual style on `mixed` sections so the two
                // buttons read as separate actions, not a default + variant.
                variant={meta.kind === 'mixed' ? 'secondary' : 'default'}
                onClick={() => pdfInputRef.current?.click()}
                disabled={uploadMutation.isPending}
                className="gap-2"
              >
                {uploadMutation.isPending ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <FileText className="h-4 w-4" />
                )}
                Subir PDF
              </Button>
            )}
            <p className="text-xs text-muted-foreground flex items-center gap-1">
              <Info className="h-3 w-3" />
              Máx. 15 MB por archivo
              {meta.kind === 'mixed' &&
                ' · El PDF que subas se mostrará en el botón "Ver en PDF" de la página pública'}
            </p>
          </div>
        </CardContent>
      </Card>

      {/* ---------- Existing files list ---------- */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <HardDrive className="h-4 w-4 text-muted-foreground" />
            Archivos en el servidor
            <Badge variant="secondary" className="ml-1">{files.length}</Badge>
          </CardTitle>
          <CardDescription>
            Los cambios se aplican inmediatamente para todos los visitantes del dominio actual.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex items-center gap-2 text-muted-foreground text-sm py-8 justify-center">
              <Loader2 className="h-4 w-4 animate-spin" />
              Cargando archivos...
            </div>
          ) : error ? (
            <div className="text-sm text-destructive py-4">
              Error al cargar la lista: {(error as Error).message}
              <Button variant="link" size="sm" onClick={() => refetch()} className="ml-2">
                Reintentar
              </Button>
            </div>
          ) : files.length === 0 ? (
            <p className="text-sm text-muted-foreground py-8 text-center">
              No hay archivos en esta sección todavía. Usa el botón "Subir" para agregar.
            </p>
          ) : (() => {
            // Split files by type so mixed sections (Bases) can show
            // PDFs as rows AND images as thumbnails simultaneously.
            const pdfFiles = files.filter((f) => isPdfFile(f.name));
            const imageFiles = files.filter((f) => !isPdfFile(f.name));
            return (
              <div className="space-y-6">
                {/* PDF list (only when at least one PDF exists) */}
                {pdfFiles.length > 0 && (
                  <div className="space-y-2">
                    {meta.kind === 'mixed' && (
                      <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                        PDF descargable {pdfFiles.length > 1 && '(la página pública usa el primero)'}
                      </p>
                    )}
                    <ul className="divide-y divide-border rounded-md border border-border">
                      {pdfFiles.map((file, idx) => (
                        <li
                          key={file.name}
                          className="flex items-center justify-between gap-3 px-3 py-2"
                        >
                          <a
                            href={file.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex items-center gap-2 min-w-0 flex-1 hover:text-primary"
                          >
                            <FileText className="h-4 w-4 shrink-0 text-primary" />
                            <span className="truncate text-sm font-medium">{file.name}</span>
                            {idx === 0 && pdfFiles.length > 1 && (
                              <Badge variant="outline" className="shrink-0 text-[10px]">
                                en uso
                              </Badge>
                            )}
                            <span className="text-xs text-muted-foreground shrink-0 ml-auto">
                              {formatBytes(file.size)}
                            </span>
                          </a>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="text-destructive hover:text-destructive hover:bg-destructive/10"
                            onClick={() => setConfirmDelete(file)}
                            aria-label={`Eliminar ${file.name}`}
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}

                {/* Image grid (only when at least one image exists) */}
                {imageFiles.length > 0 && (
                  <div className="space-y-2">
                    {meta.kind === 'mixed' && (
                      <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                        Imágenes
                      </p>
                    )}
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                      {imageFiles.map((file) => (
                        <FileCard
                          key={file.name}
                          file={file}
                          onDelete={() => setConfirmDelete(file)}
                          isDeleting={deleteMutation.isPending && deleteMutation.variables?.name === file.name}
                        />
                      ))}
                    </div>
                  </div>
                )}
              </div>
            );
          })()}
        </CardContent>
      </Card>

      {/* ---------- Confirm delete dialog ---------- */}
      <AlertDialog
        open={confirmDelete !== null}
        onOpenChange={(open) => !open && setConfirmDelete(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>¿Eliminar archivo?</AlertDialogTitle>
            <AlertDialogDescription>
              Se eliminará permanentemente <strong>{confirmDelete?.name}</strong> del servidor.
              Esta acción no se puede deshacer.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleConfirmDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Eliminar
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
};

// ============= Single file card (image preview) =============

interface FileCardProps {
  file: UploadedFile;
  onDelete: () => void;
  isDeleting: boolean;
}

/** Thumbnail card with hover overlay + delete button. */
const FileCard = ({ file, onDelete, isDeleting }: FileCardProps) => {
  return (
    <div
      className={cn(
        'group relative overflow-hidden rounded-lg border border-border bg-card',
        'transition-shadow hover:shadow-md',
        isDeleting && 'opacity-50 pointer-events-none'
      )}
    >
      <a
        href={file.url}
        target="_blank"
        rel="noopener noreferrer"
        className="block aspect-[9/16] w-full bg-muted overflow-hidden"
        aria-label={`Ver ${file.name} en tamaño completo`}
      >
        <img
          src={file.url}
          alt={file.alt}
          loading="lazy"
          className="h-full w-full object-contain transition-transform group-hover:scale-105"
        />
      </a>
      <div className="p-2 space-y-1">
        <p className="text-xs font-medium truncate" title={file.name}>
          <FileImage className="h-3 w-3 inline mr-1 text-muted-foreground" />
          {file.name}
        </p>
        <div className="flex items-center justify-between gap-2">
          <span className="text-[10px] text-muted-foreground">{formatBytes(file.size)}</span>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="h-7 w-7 text-destructive hover:text-destructive hover:bg-destructive/10"
            onClick={onDelete}
            disabled={isDeleting}
            aria-label={`Eliminar ${file.name}`}
          >
            {isDeleting ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Trash2 className="h-3.5 w-3.5" />}
          </Button>
        </div>
      </div>
    </div>
  );
};

// ============= Top-level component =============

/**
 * AdminUploads
 * Wrapper card with sub-tabs (one per section).
 */
const AdminUploads = () => {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Upload className="h-5 w-5 text-primary" />
          Subida de archivos
        </CardTitle>
        <CardDescription>
          Sube imágenes y PDFs directamente al servidor. Los cambios se reflejan al
          instante en las páginas correspondientes — no requiere re-deploy.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <Tabs defaultValue="eventos" className="space-y-4">
          <TabsList
            className="grid w-full"
            style={{ gridTemplateColumns: `repeat(${SECTIONS.length}, minmax(0, 1fr))` }}
          >
            {SECTIONS.map((s) => (
              <TabsTrigger key={s.id} value={s.id} className="gap-2">
                <s.Icon className="h-4 w-4" />
                <span className="hidden sm:inline">{s.label}</span>
              </TabsTrigger>
            ))}
          </TabsList>
          {SECTIONS.map((s) => (
            <TabsContent key={s.id} value={s.id}>
              <SectionPanel meta={s} />
            </TabsContent>
          ))}
        </Tabs>
      </CardContent>
    </Card>
  );
};

export default AdminUploads;
