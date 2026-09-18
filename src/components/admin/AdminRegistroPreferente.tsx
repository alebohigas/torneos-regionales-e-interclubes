/**
 * AdminRegistroPreferente
 * ---------------------------------------------------------------------
 * Sub-tab dentro de /admin → Pre-Registro.
 *
 * Permite al administrador configurar la "ventana de registro preferente":
 * un rango de fechas en el que SOLO los socios de clubes previamente
 * autorizados pueden pre-registrarse al torneo. Fuera de esa ventana el
 * formulario público vuelve a comportarse normal (todos los clubes).
 *
 * UI:
 *   1) Rango global (fecha_inicio + fecha_fin)
 *   2) Checkbox "Todos los clubes usan el mismo rango de fechas"
 *   3) Buscador + checklist de clubes disponibles (tabla `clubs`)
 *      con checks para agregar/quitar del `clubs_registro`.
 *   4) Si el checkbox está apagado, cada club seleccionado expone
 *      sus propias fechas inicio/fin editables.
 */

import { useEffect, useMemo, useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Checkbox } from '@/components/ui/checkbox';
import { Loader2, Save, CalendarRange, Search, RotateCcw } from 'lucide-react';
import { useTorneoId } from '@/hooks/useTorneoId';
import { useToast } from '@/hooks/use-toast';
import { useRegistroPreferente, useSaveRegistroPreferente, type PreferenteClub } from '@/hooks/useRegistroPreferente';
import { getClubsUrl } from '@/config/api';
import { getSuperAdminPassword } from '@/lib/superAdminAuth';

/** Row shape returned by /api/clubs.php. */
interface ClubRow { id: number; nombre: string }

/** Local editable state per authorized club. */
interface EditRow {
  id: number;
  nombre: string;
  fecha_inicio: string;
  fecha_fin: string;
}

const AdminRegistroPreferente = () => {
  const { torneoId } = useTorneoId();
  const { data, isLoading } = useRegistroPreferente();
  const saveMut = useSaveRegistroPreferente();
  const { toast } = useToast();

  /** Full clubs catalog for the searchable checklist. */
  const [allClubs, setAllClubs] = useState<ClubRow[]>([]);
  /** Filter query for the checklist. */
  const [query, setQuery] = useState('');
  /** Global window inputs (bound to <input type=date>). */
  const [fechaInicio, setFechaInicio] = useState('');
  const [fechaFin, setFechaFin] = useState('');
  /** true => all authorized clubs share the global window. */
  const [sameRange, setSameRange] = useState(true);
  /** Selected clubs (by clubid) with their per-club window. */
  const [selected, setSelected] = useState<Record<number, EditRow>>({});

  // Cargar catálogo completo de clubes una sola vez.
  useEffect(() => {
    fetch(getClubsUrl())
      .then(r => r.json())
      .then(j => setAllClubs(Array.isArray(j?.clubs) ? j.clubs : []))
      .catch(() => setAllClubs([]));
  }, []);

  // Hidratar estado local con lo que devuelve el servidor.
  useEffect(() => {
    if (!data) return;
    setFechaInicio(data.fecha_inicio || '');
    setFechaFin(data.fecha_fin || '');
    setSameRange((data.same_range ?? 1) === 1);
    const map: Record<number, EditRow> = {};
    (data.clubs || []).forEach((c: PreferenteClub) => {
      map[c.id] = {
        id: c.id,
        nombre: c.nombre,
        fecha_inicio: c.fecha_inicio || '',
        fecha_fin: c.fecha_fin || '',
      };
    });
    setSelected(map);
  }, [data]);

  /** Filtered checklist for display. */
  const filteredClubs = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return allClubs;
    return allClubs.filter(c => c.nombre.toLowerCase().includes(q));
  }, [allClubs, query]);

  /** Toggle a single club in the authorized set. */
  const toggleClub = (c: ClubRow, on: boolean) => {
    setSelected(prev => {
      const next = { ...prev };
      if (on) {
        next[c.id] = { id: c.id, nombre: c.nombre, fecha_inicio: '', fecha_fin: '' };
      } else {
        delete next[c.id];
      }
      return next;
    });
  };

  /** Update per-club date field. */
  const updateClubDate = (id: number, key: 'fecha_inicio' | 'fecha_fin', v: string) => {
    setSelected(prev => ({ ...prev, [id]: { ...prev[id], [key]: v } }));
  };

  /**
   * toggleAllClubs
   * Selecciona (on=true) o deselecciona (on=false) TODOS los clubes
   * actualmente visibles según el filtro de búsqueda. Si no hay filtro,
   * aplica al catálogo completo. Conserva las fechas por-club ya capturadas.
   */
  const toggleAllClubs = (on: boolean) => {
    setSelected(prev => {
      const next = { ...prev };
      filteredClubs.forEach(c => {
        if (on) {
          next[c.id] = next[c.id] ?? { id: c.id, nombre: c.nombre, fecha_inicio: '', fecha_fin: '' };
        } else {
          delete next[c.id];
        }
      });
      return next;
    });
  };

  /**
   * resetFechas
   * Limpia el rango global y las fechas individuales de cada club
   * autorizado. No borra la selección de clubes; el cambio se aplica
   * al guardar.
   */
  const resetFechas = () => {
    setFechaInicio('');
    setFechaFin('');
    setSelected(prev => {
      const next: Record<number, EditRow> = {};
      Object.values(prev).forEach(r => {
        next[r.id] = { ...r, fecha_inicio: '', fecha_fin: '' };
      });
      return next;
    });
    toast({ title: 'Fechas reiniciadas', description: 'Presiona Guardar para aplicar los cambios.' });
  };

  /** POST the whole config. */
  const onSave = () => {
    if (!torneoId) {
      toast({ title: 'Configura primero el Torneo ID', variant: 'destructive' });
      return;
    }
    if ((fechaInicio && !fechaFin) || (!fechaInicio && fechaFin)) {
      toast({ title: 'Rango incompleto', description: 'Captura fecha inicio y fin, o deja ambas vacías.', variant: 'destructive' });
      return;
    }
    if (fechaInicio && fechaFin && fechaInicio > fechaFin) {
      toast({ title: 'Rango inválido', description: 'La fecha de inicio no puede ser posterior a la fecha fin.', variant: 'destructive' });
      return;
    }
    const clubsPayload = Object.values(selected).map(r => ({
      clubid: r.id,
      fecha_inicio: sameRange ? null : (r.fecha_inicio || null),
      fecha_fin:    sameRange ? null : (r.fecha_fin    || null),
    }));
    saveMut.mutate(
      {
        torneoid: parseInt(torneoId, 10),
        fecha_inicio: fechaInicio || null,
        fecha_fin: fechaFin || null,
        same_range: sameRange ? 1 : 0,
        clubs: clubsPayload,
        password: getSuperAdminPassword(),
      },
      {
        onSuccess: () => toast({ title: 'Registro preferente guardado' }),
        onError: (err: any) => toast({ title: 'Error al guardar', description: err.message, variant: 'destructive' }),
      }
    );
  };

  const selectedCount = Object.keys(selected).length;
  const activeNow = !!data?.active_now;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <CalendarRange className="h-5 w-5 text-primary" />
          Pre-Registro · Registro preferente
        </CardTitle>
        <CardDescription>
          Define un rango de fechas donde SOLO los socios de los clubes
          autorizados pueden pre-registrarse. Al terminar el rango, el
          formulario público vuelve a aceptar cualquier club de la lista general.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
        {isLoading ? (
          <div className="flex items-center gap-2 text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin" /> Cargando…
          </div>
        ) : (
          <>
            {/* Estado actual */}
            <div className="text-sm">
              Estado hoy:{' '}
              <span className={activeNow ? 'text-green-600 font-medium' : 'text-muted-foreground'}>
                {activeNow ? 'Ventana preferente ACTIVA' : 'Ventana preferente cerrada (abierto al público)'}
              </span>
            </div>

            {/* Rango global */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
              <div className="space-y-1.5">
                <Label htmlFor="fi">Fecha inicio (global)</Label>
                <Input id="fi" type="date" value={fechaInicio} onChange={e => setFechaInicio(e.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="ff">Fecha fin (global)</Label>
                <Input id="ff" type="date" value={fechaFin} onChange={e => setFechaFin(e.target.value)} />
              </div>
              <div className="flex items-center gap-2 pt-6">
                <Switch id="same" checked={sameRange} onCheckedChange={setSameRange} />
                <Label htmlFor="same" className="cursor-pointer">
                  Todos los clubes usan el mismo rango
                </Label>
              </div>
              {/* Limpia el rango global y las fechas por club (requiere Guardar) */}
              <div className="pt-6">
                <Button
                  type="button"
                  variant="outline"
                  onClick={resetFechas}
                  className="gap-2 bg-primary/10 hover:bg-primary/20 border-primary/20"
                >
                  <RotateCcw className="h-4 w-4" />
                  Resetear fechas
                </Button>
              </div>
            </div>

            {/* Buscador de clubes */}
            <div className="space-y-2">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <Label>Clubes autorizados ({selectedCount})</Label>
                {/* Selección masiva — aplica a los clubes visibles/filtrados */}
                <div className="flex items-center gap-2">
                  <Switch
                    id="all-clubs"
                    checked={filteredClubs.length > 0 && filteredClubs.every(c => !!selected[c.id])}
                    onCheckedChange={toggleAllClubs}
                  />
                  <Label htmlFor="all-clubs" className="cursor-pointer text-sm">
                    Seleccionar todos {query.trim() ? '(filtrados)' : ''}
                  </Label>
                </div>
              </div>
              <div className="relative">
                <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="Buscar club por nombre…"
                  value={query}
                  onChange={e => setQuery(e.target.value)}
                  className="pl-8"
                />
              </div>

              <div className="max-h-96 overflow-y-auto rounded-md border">
                <table className="w-full text-sm">
                  <thead className="bg-muted/50 sticky top-0">
                    <tr>
                      <th className="text-left p-2 w-10"></th>
                      <th className="text-left p-2">Club</th>
                      {!sameRange && (
                        <>
                          <th className="text-left p-2 w-40">Fecha inicio</th>
                          <th className="text-left p-2 w-40">Fecha fin</th>
                        </>
                      )}
                    </tr>
                  </thead>
                  <tbody>
                    {filteredClubs.map(c => {
                      const on = !!selected[c.id];
                      const row = selected[c.id];
                      return (
                        <tr key={c.id} className="border-t">
                          <td className="p-2">
                            <Checkbox
                              checked={on}
                              onCheckedChange={v => toggleClub(c, !!v)}
                            />
                          </td>
                          <td className="p-2">{c.nombre}</td>
                          {!sameRange && (
                            <>
                              <td className="p-2">
                                <Input
                                  type="date"
                                  disabled={!on}
                                  value={row?.fecha_inicio || ''}
                                  onChange={e => updateClubDate(c.id, 'fecha_inicio', e.target.value)}
                                />
                              </td>
                              <td className="p-2">
                                <Input
                                  type="date"
                                  disabled={!on}
                                  value={row?.fecha_fin || ''}
                                  onChange={e => updateClubDate(c.id, 'fecha_fin', e.target.value)}
                                />
                              </td>
                            </>
                          )}
                        </tr>
                      );
                    })}
                    {filteredClubs.length === 0 && (
                      <tr><td colSpan={sameRange ? 2 : 4} className="p-4 text-center text-muted-foreground">
                        Sin resultados
                      </td></tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>

            <div className="flex justify-end">
              <Button onClick={onSave} disabled={saveMut.isPending} className="gap-2">
                {saveMut.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                Guardar registro preferente
              </Button>
            </div>
          </>
        )}
      </CardContent>
    </Card>
  );
};

export default AdminRegistroPreferente;