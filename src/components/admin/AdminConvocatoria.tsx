/**
 * AdminConvocatoria
 * Admin panel tab for managing convocatoria sections
 * Supports: reordering (drag-and-drop), enable/disable, content editing
 */

import { useState } from 'react';
import { DragDropContext, Droppable, Draggable, DropResult } from '@hello-pangea/dnd';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  GripVertical,
  Eye,
  EyeOff,
  ChevronDown,
  ChevronUp,
  FileText,
  AlertTriangle,
  Database,
  CircleSlash,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { useConvocatoriaSections } from '@/hooks/useConvocatoriaSections';
import { useConvocatoriaContent } from '@/hooks/useConvocatoriaContent';
import { useValorStable } from '@/hooks/useValorStable';
import StablefordTable from '@/components/shared/StablefordTable';
import SectionEditor from '@/components/admin/convocatoria/SectionEditor';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';

// ============= Section icons by ID =============

const sectionIcons: Record<string, string> = {
  descripcion: '📝',
  elegibilidad: '✅',
  costos: '💰',
  categorias: '🏷️',
  premiacion: '🏆',
  calendario: '📅',
  reglas: '⚖️',
  competencias: '⚡',
  desempates: '⚖️',
  stableford: '🔢',
};

// ============= Stableford (BD: torneos.valorstable) =============

/**
 * StablefordValuesPanel
 * Panel de solo lectura que muestra SIEMPRE los valores Stableford del
 * torneo activo leídos de la BD (`torneos.valorstable` por `torneoid`).
 * No es editable desde /admin: cada torneo nuevo carga sus valores en
 * automático al crearse en la base de datos.
 */
const StablefordValuesPanel = () => {
  const { rows, loading } = useValorStable();

  return (
    <div className="space-y-2">
      <p className="text-xs text-muted-foreground">
        Valores tomados automáticamente de la base de datos
        (<code>torneos.valorstable</code>) para el torneo activo. Solo lectura.
      </p>

      {loading ? (
        <p className="text-sm text-muted-foreground">Cargando valores…</p>
      ) : rows.length === 0 ? (
        <p className="text-sm text-amber-600 flex items-center gap-1">
          <AlertTriangle className="h-3 w-3" />
          Este torneo aún no tiene fila en <code>valorstable</code>.
        </p>
      ) : (
        /* Formato unificado (ver StablefordTable). */
        <StablefordTable rows={rows} />
      )}
    </div>
  );
};

// ============= Component =============

const AdminConvocatoria = () => {
  const {
    sections,
    setSectionEnabled,
    reorderSections,
  } = useConvocatoriaSections();
  /** DB-backed status per section (drives the BD / Vacío badge). */
  const { hasContent, bySectionId, saveSection } = useConvocatoriaContent();

  const [expandedSection, setExpandedSection] = useState<string | null>(null);

  /**
   * Toggle de visibilidad de una sección.
   *
   * Persiste el flag en la BD (`convocatoria_content.enabled`) para que
   * aplique en TODOS los dispositivos, y mantiene el override local para
   * respuesta inmediata en la UI. Si la sección aún no tiene fila en BD
   * (p. ej. "Valores Stableford", cuyos datos viven en torneos.valorstable),
   * se crea una fila mínima solo para guardar la visibilidad.
   */
  const handleToggleEnabled = async (
    sectionId: string,
    enabled: boolean,
    sortOrder: number,
    label: string,
  ) => {
    setSectionEnabled(sectionId, enabled);
    const row = bySectionId.get(sectionId);
    await saveSection({
      sectionId,
      sectionType: row?.section_type ?? sectionId,
      title: row?.title ?? label,
      content: row?.content ?? {},
      sortOrder: row?.sort_order ?? Math.round(sortOrder),
      enabled,
    });
  };

  /** Handle drag end for reordering */
  const handleDragEnd = (result: DropResult) => {
    if (!result.destination) return;

    const items = Array.from(sections);
    const [reordered] = items.splice(result.source.index, 1);
    items.splice(result.destination.index, 0, reordered);

    reorderSections(items.map((s) => s.id));
  };

  /** Visibilidad efectiva: BD manda si existe fila, si no el flag local. */
  const isEnabled = (id: string, localEnabled: boolean) =>
    bySectionId.get(id)?.enabled ?? localEnabled;

  /** Valores Stableford del torneo activo (para el badge de la fila). */
  const { rows: stablefordRows } = useValorStable();

  const enabledCount = sections.filter((s) => isEnabled(s.id, s.enabled)).length;
  const disabledCount = sections.length - enabledCount;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <FileText className="h-5 w-5 text-primary" />
          Secciones de Convocatoria
        </CardTitle>
        <CardDescription>
          Arrastra para reordenar, activa/desactiva secciones y edita el contenido.
          Las secciones deshabilitadas no se muestran en la página. El
          contenido es estrictamente DB-only: si una sección está vacía en BD,
          NO se muestra en la página pública (no hay fallback a mocks).
        </CardDescription>
        <div className="flex gap-3 mt-2">
          <Badge variant="default" className="gap-1">
            <Eye className="h-3 w-3" />
            {enabledCount} visibles
          </Badge>
          <Badge variant="secondary" className="gap-1">
            <EyeOff className="h-3 w-3" />
            {disabledCount} ocultas
          </Badge>
        </div>
      </CardHeader>
      <CardContent>
        <DragDropContext onDragEnd={handleDragEnd}>
          <Droppable droppableId="convocatoria-sections">
            {(provided) => (
              <div
                {...provided.droppableProps}
                ref={provided.innerRef}
                className="space-y-2"
              >
                {sections.map((section, index) => (
                  <Draggable key={section.id} draggableId={section.id} index={index}>
                    {(provided, snapshot) => {
                      /** Visibilidad efectiva de esta sección (BD > local). */
                      const enabled = isEnabled(section.id, section.enabled);
                      return (
                      <div
                        ref={provided.innerRef}
                        {...provided.draggableProps}
                        className={cn(
                          'rounded-lg border transition-all',
                          snapshot.isDragging
                            ? 'shadow-lg border-primary bg-primary/5'
                            : enabled
                              ? 'border-border bg-card'
                              : 'border-border/50 bg-muted/30 opacity-70'
                        )}
                      >
                        {/* Section row */}
                        <div className="flex items-center gap-3 px-4 py-3">
                          {/* Drag handle */}
                          <div
                            {...provided.dragHandleProps}
                            className="cursor-grab active:cursor-grabbing text-muted-foreground hover:text-foreground"
                          >
                            <GripVertical className="h-5 w-5" />
                          </div>

                          {/* Icon */}
                          <span className="text-xl">{sectionIcons[section.id] || '📄'}</span>

                          {/* Label and status */}
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2">
                              <span className={cn(
                                'font-medium text-sm',
                                !enabled && 'text-muted-foreground line-through'
                              )}>
                                {section.label}
                              </span>
                              {/* BD / Vacío badge: green when a non-empty
                                  convocatoria_content row exists for the
                                  active torneoid; grey otherwise. */}
                              {(section.id === 'stableford'
                                ? stablefordRows.length > 0
                                : hasContent(section.id)) ? (
                                <Badge variant="outline" className="text-xs gap-1 text-emerald-700 border-emerald-300 bg-emerald-50">
                                  <Database className="h-3 w-3" />
                                  BD
                                </Badge>
                              ) : (
                                <Badge variant="outline" className="text-xs gap-1 text-muted-foreground">
                                  <CircleSlash className="h-3 w-3" />
                                  Vacío
                                </Badge>
                              )}
                              {!enabled && (
                                <Badge variant="outline" className="text-xs gap-1 text-amber-600 border-amber-300">
                                  <AlertTriangle className="h-3 w-3" />
                                  Oculta
                                </Badge>
                              )}
                            </div>
                          </div>

                          {/* Toggle enable/disable */}
                          <Switch
                            checked={enabled}
                            onCheckedChange={(checked) =>
                              handleToggleEnabled(
                                section.id,
                                checked,
                                section.order,
                                section.label,
                              )
                            }
                          />

                          {/* Expand/collapse for content editing */}
                          <Collapsible
                            open={expandedSection === section.id}
                            onOpenChange={(open) =>
                              setExpandedSection(open ? section.id : null)
                            }
                          >
                            <CollapsibleTrigger asChild>
                              <Button variant="ghost" size="icon" className="h-8 w-8">
                                {expandedSection === section.id ? (
                                  <ChevronUp className="h-4 w-4" />
                                ) : (
                                  <ChevronDown className="h-4 w-4" />
                                )}
                              </Button>
                            </CollapsibleTrigger>
                          </Collapsible>
                        </div>

                        {/* Expandable structured editor with live preview.
                            Renders the proper editor for the section shape
                            (text / items repeater / JSON fallback) plus a
                            preview that uses the SAME public Section
                            component for visual fidelity. */}
                        {/* Valores Stableford: se muestran SIEMPRE (solo lectura,
                            provienen de la BD). El resto de secciones solo al
                            expandirse. */}
                        {(expandedSection === section.id || section.id === 'stableford') && (
                          <div className="px-4 pb-4 border-t border-border/50 pt-3">
                            {section.id === 'stableford' ? (
                              <StablefordValuesPanel />
                            ) : (
                              <SectionEditor
                                sectionId={section.id}
                                label={section.label}
                                sortOrder={section.order}
                              />
                            )}
                            {!enabled && (
                              <p className="text-xs text-amber-600 mt-2 flex items-center gap-1">
                                <AlertTriangle className="h-3 w-3" />
                                Sección oculta - no se mostrará en la página aunque tenga contenido
                              </p>
                            )}
                          </div>
                        )}
                      </div>
                      );
                    }}
                  </Draggable>
                ))}
                {provided.placeholder}
              </div>
            )}
          </Droppable>
        </DragDropContext>
      </CardContent>
    </Card>
  );
};

export default AdminConvocatoria;
