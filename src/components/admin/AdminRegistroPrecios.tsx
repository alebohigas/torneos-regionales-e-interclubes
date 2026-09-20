/**
 * AdminRegistroPrecios
 * Pestaña admin para configurar los PRECIOS de inscripción.
 * Filtra por **tipo de socio**, **género** y **rango de edad** — para
 * permitir precios diferenciados (p.ej. titulares hombres/mujeres,
 * dependientes menores de 18, etc.). Las restricciones de elegibilidad
 * de categoría (por hándicap) viven en otra pestaña ("Categorías
 * elegibles" → tabla `categorias_reglas`).
 *
 * Persistencia: replace-all por torneo vía POST /api/registro_precios.php.
 */

import { useEffect, useMemo, useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Loader2, Save, DollarSign, Plus, Trash2, ExternalLink } from 'lucide-react';
import { useTorneoId } from '@/hooks/useTorneoId';
import { useCategories } from '@/hooks/usePlayersData';
import {
  useRegistroPrecios,
  useSaveRegistroPrecios,
  type RegistroPrecioRule,
} from '@/hooks/useRegistroPrecios';
import { useToast } from '@/hooks/use-toast';

/** Etiquetas humanas para el <Select> de tipo de socio. */
const TIPO_SOCIO_OPTIONS: { value: string; label: string }[] = [
  { value: 'ANY',         label: 'Cualquiera' },
  { value: 'SOCIO',       label: 'Socio (cualquier subtipo)' },
  { value: 'TITULAR',     label: 'Socio Titular' },
  { value: 'EMERITO',     label: 'Socio Emérito' },
  { value: 'DEPENDIENTE', label: 'Socio Dependiente' },
  { value: 'NO_SOCIO',    label: 'No socio' },
  { value: 'INVITADO',    label: 'Invitado' },
  { value: 'FORANEO',     label: 'Foráneo' },
];

/** Opciones del <Select> de género para una regla de precio. */
const GENERO_OPTIONS: { value: string; label: string }[] = [
  { value: 'ANY', label: 'Cualquiera' },
  { value: 'M',   label: 'Caballero (M)' },
  { value: 'F',   label: 'Dama (F)' },
];

/** Una regla nueva en blanco — usada al pulsar "Agregar regla". */
const blankRule = (order: number): Partial<RegistroPrecioRule> => ({
  id: 0,
  etiqueta: '',
  categoria: null,
  tipo_socio: null,
  genero: null,
  edad_min: null,
  edad_max: null,
  hcp_min: null,
  hcp_max: null,
  precio: 0,
  moneda: 'MXN',
  incluye: '',
  prioridad: 0,
  display_order: order,
  is_active: 1,
});

const AdminRegistroPrecios = () => {
  const { torneoId } = useTorneoId();
  const { data, isLoading } = useRegistroPrecios();
  const { data: categories = [] } = useCategories();
  const save = useSaveRegistroPrecios();
  const { toast } = useToast();

  /** Copia local editable. */
  const [rows, setRows] = useState<Partial<RegistroPrecioRule>[]>([]);

  /** Sincroniza al cargar / refrescar. */
  useEffect(() => {
    if (data?.rules) {
      setRows([...data.rules].sort((a, b) => a.display_order - b.display_order));
    }
  }, [data?.rules]);

  /** Actualiza un campo de una regla por índice. */
  const update = (idx: number, patch: Partial<RegistroPrecioRule>) => {
    setRows(prev => prev.map((r, i) => (i === idx ? { ...r, ...patch } : r)));
  };

  const addRule = () => {
    setRows(prev => [...prev, blankRule((prev.length + 1) * 10)]);
  };

  const removeRule = (idx: number) => {
    setRows(prev => prev.filter((_, i) => i !== idx));
  };

  const onSave = () => {
    if (!torneoId) {
      toast({ title: 'Configura primero el Torneo ID', variant: 'destructive' });
      return;
    }
    save.mutate(
      { torneoid: parseInt(torneoId, 10), rules: rows, password: 'admin2025' },
      {
        onSuccess: () => toast({ title: 'Precios guardados', description: `${rows.length} regla(s).` }),
        onError: (err: any) =>
          toast({ title: 'Error al guardar', description: err.message, variant: 'destructive' }),
      }
    );
  };

  const activeCount = useMemo(() => rows.filter(r => !!r.is_active).length, [rows]);

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <DollarSign className="h-5 w-5 text-primary" />
            Pre-Registro · Precios de inscripción
          </CardTitle>
          <CardDescription>
            Define el costo de inscripción por <strong>categoría, género, edad</strong>
            y, cuando aplique, tipo de socio. El detalle incluido se muestra al
            jugador junto al monto. Las restricciones de elegibilidad viven en{' '}
            <strong>Categorías elegibles</strong>.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex items-center gap-2 text-muted-foreground">
              <Loader2 className="h-4 w-4 animate-spin" /> Cargando…
            </div>
          ) : (
            <>
              <div className="flex items-center justify-between mb-3 flex-wrap gap-2">
                <p className="text-sm text-muted-foreground">
                  {activeCount} de {rows.length} regla(s) activas
                </p>
                <div className="flex gap-2 flex-wrap">
                  <Button asChild variant="outline" size="sm" className="gap-2">
                    <a href="/registro" target="_blank" rel="noopener noreferrer">
                      <ExternalLink className="h-4 w-4" /> Ver formulario
                    </a>
                  </Button>
                  <Button onClick={addRule} variant="outline" size="sm" className="gap-2">
                    <Plus className="h-4 w-4" /> Agregar regla
                  </Button>
                  <Button onClick={onSave} disabled={save.isPending} className="gap-2">
                    {save.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                    Guardar
                  </Button>
                </div>
              </div>

              {rows.length === 0 ? (
                <div className="border border-dashed rounded-md p-8 text-center text-muted-foreground">
                  Sin reglas de precio para este torneo. Pulsa <strong>Agregar regla</strong>.
                </div>
              ) : (
                <div className="overflow-x-auto rounded-md border">
                  <table className="w-full text-sm">
                    <thead className="bg-muted/50">
                      <tr>
                        <th className="text-left p-2 min-w-[160px]">Etiqueta</th>
                        <th className="text-left p-2 min-w-[180px]">Categoría</th>
                        <th className="text-left p-2 min-w-[160px]">Tipo socio</th>
                        <th className="text-left p-2 w-32">Género</th>
                        <th className="text-center p-2 w-20">Edad mín</th>
                        <th className="text-center p-2 w-20">Edad máx</th>
                        <th className="text-right p-2 w-28">Precio</th>
                        <th className="text-center p-2 w-20">Moneda</th>
                        <th className="text-left p-2 min-w-[160px]">Incluye</th>
                        <th className="text-center p-2 w-20">Activa</th>
                        <th className="text-center p-2 w-10"></th>
                      </tr>
                    </thead>
                    <tbody>
                      {rows.map((r, idx) => (
                        <tr key={idx} className="border-t align-top">
                          <td className="p-2">
                            <Input
                              value={r.etiqueta || ''}
                              onChange={e => update(idx, { etiqueta: e.target.value })}
                              placeholder="Ej: Socio Titular Caballero"
                            />
                          </td>
                          <td className="p-2">
                            <Select
                              value={r.categoria ?? 'ANY'}
                              onValueChange={v => update(idx, { categoria: v === 'ANY' ? null : v })}
                            >
                              <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
                              <SelectContent className="max-h-[280px]">
                                <SelectItem value="ANY">Cualquier categoría</SelectItem>
                                {categories.map(c => (
                                  <SelectItem key={c.id} value={c.name}>{c.name}</SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                          </td>
                          <td className="p-2">
                            <Select
                              value={r.tipo_socio ?? 'ANY'}
                              onValueChange={v => update(idx, { tipo_socio: v === 'ANY' ? null : v })}
                            >
                              <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
                              <SelectContent>
                                {TIPO_SOCIO_OPTIONS.map(o => (
                                  <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                          </td>
                          <td className="p-2">
                            <Select
                              value={r.genero ?? 'ANY'}
                              onValueChange={v => update(idx, { genero: v === 'ANY' ? null : v })}
                            >
                              <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
                              <SelectContent>
                                {GENERO_OPTIONS.map(o => (
                                  <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                          </td>
                          <td className="p-2">
                            <Input
                              type="number"
                              inputMode="numeric"
                              min={0}
                              value={r.edad_min ?? ''}
                              onChange={e => update(idx, {
                                edad_min: e.target.value === '' ? null : parseInt(e.target.value, 10),
                              })}
                              placeholder="—"
                              className="w-20 text-center [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                            />
                          </td>
                          <td className="p-2">
                            <Input
                              type="number"
                              inputMode="numeric"
                              min={0}
                              value={r.edad_max ?? ''}
                              onChange={e => update(idx, {
                                edad_max: e.target.value === '' ? null : parseInt(e.target.value, 10),
                              })}
                              placeholder="—"
                              className="w-20 text-center [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                            />
                          </td>
                          <td className="p-2">
                            <Input
                              type="number"
                              inputMode="decimal"
                              step="0.01"
                              value={r.precio ?? 0}
                              onChange={e => update(idx, { precio: parseFloat(e.target.value) || 0 })}
                              className="w-28 text-right [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                            />
                          </td>
                          <td className="p-2">
                            <Input
                              value={r.moneda || 'MXN'}
                              maxLength={3}
                              onChange={e => update(idx, { moneda: e.target.value.toUpperCase() })}
                              className="w-20 text-center"
                            />
                          </td>
                          <td className="p-2">
                            <Input
                              value={r.incluye || ''}
                              onChange={e => update(idx, { incluye: e.target.value })}
                              placeholder="Incluye carrito, kit, comida…"
                            />
                          </td>
                          <td className="p-2 text-center">
                            <Switch
                              checked={!!r.is_active}
                              onCheckedChange={v => update(idx, { is_active: v ? 1 : 0 })}
                            />
                          </td>
                          <td className="p-2 text-center">
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              onClick={() => removeRule(idx)}
                              aria-label="Eliminar regla"
                            >
                              <Trash2 className="h-4 w-4 text-destructive" />
                            </Button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              <p className="text-xs text-muted-foreground mt-3 leading-relaxed">
                <strong>Tip:</strong> configura la categoría, género y edades de cada
                costo. Deja una regla con categoría y tipo de socio en{' '}
                <em>Cualquiera</em> (y sin filtros de género/edad) al final
                como precio por defecto si algún jugador no entra en ninguna
                regla específica. Para precios diferenciados por sexo o por
                menores de 18, define <em>Género</em> y/o <em>Edad máx</em>
                en la regla — la más específica gana automáticamente.
              </p>
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default AdminRegistroPrecios;
