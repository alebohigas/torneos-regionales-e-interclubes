/**
 * AdminHistorial
 * ------------------------------------------------------------------
 * Admin tab that configures the public /historial page:
 *  1) Hasta 5 ediciones anteriores (AÑO + torneo_id) con sus resultados.
 *  2) Temporadas / giras publicadas (gira.giraid) donde se elige si se
 *     muestra el Ranking Final y/o las Copas de esa gira.
 *
 * Storage: `site_config.historial_config = { editions: [...], seasons: [...] }`
 */

import { useEffect, useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { History, Loader2, Plus, Save, Trash2, Trophy } from 'lucide-react';
import {
  useSiteConfig,
  useSaveSiteConfig,
  type HistorialConfig,
  type HistorialEdition,
  type HistorialSeason,
} from '@/hooks/useSiteConfig';
import { useGirasList } from '@/hooks/useGiraData';
import { useToast } from '@/hooks/use-toast';
import { getSuperAdminPassword } from '@/lib/superAdminAuth';

/** Hard limit of past editions the admin may register. */
export const MAX_EDITIONS = 5;
/** Límite de temporadas/giras publicadas en /historial. */
export const MAX_SEASONS = 5;

const AdminHistorial = () => {
  const { data: siteConfig, isLoading } = useSiteConfig();
  const saveSiteConfig = useSaveSiteConfig();
  const { data: giras = [] } = useGirasList();
  const { toast } = useToast();

  /** Local editable copy of the editions table. */
  const [rows, setRows] = useState<HistorialEdition[]>([]);
  /** Local editable copy de las temporadas/giras publicadas. */
  const [seasons, setSeasons] = useState<HistorialSeason[]>([]);

  /** Hydrate from the server config whenever it changes. */
  useEffect(() => {
    const cfg = siteConfig?.historial_config;
    setRows(Array.isArray(cfg?.editions) ? cfg!.editions : []);
    setSeasons(Array.isArray(cfg?.seasons) ? cfg!.seasons! : []);
  }, [siteConfig?.historial_config]);

  /** Append an empty row (previous year by default), respecting the limit. */
  const addRow = () => {
    if (rows.length >= MAX_EDITIONS) return;
    const currentYear = new Date().getFullYear();
    const usedYears = rows.map(r => Number(r.year));
    let year = currentYear - 1;
    while (usedYears.includes(year)) year -= 1;
    setRows([...rows, { year, torneoId: '', label: '' }]);
  };

  /** Update one field of a row. */
  const updateRow = (idx: number, patch: Partial<HistorialEdition>) => {
    setRows(rows.map((r, i) => (i === idx ? { ...r, ...patch } : r)));
  };

  /** Remove a row from the table. */
  const removeRow = (idx: number) => setRows(rows.filter((_, i) => i !== idx));

  /** Temporadas ------------------------------------------------------- */
  const addSeason = () => {
    if (seasons.length >= MAX_SEASONS) return;
    setSeasons([...seasons, { giraId: '', name: '', showRankingFinal: true, showCopas: true }]);
  };
  const updateSeason = (idx: number, patch: Partial<HistorialSeason>) => {
    setSeasons(seasons.map((s, i) => (i === idx ? { ...s, ...patch } : s)));
  };
  const removeSeason = (idx: number) => setSeasons(seasons.filter((_, i) => i !== idx));

  /**
   * Persist to site_config. Rows without a torneo_id are dropped, years are
   * normalized to numbers and the list is sorted most-recent-first (that's
   * the order the public year selector renders). Las temporadas sin giraid
   * también se descartan.
   */
  const handleSave = () => {
    const cleaned: HistorialEdition[] = rows
      .map(r => ({
        year: Number(r.year) || 0,
        torneoId: String(r.torneoId || '').trim(),
        label: (r.label || '').trim() || undefined,
      }))
      .filter(r => r.year > 0 && r.torneoId !== '')
      .sort((a, b) => b.year - a.year)
      .slice(0, MAX_EDITIONS);

    const cleanedSeasons: HistorialSeason[] = seasons
      .map(s => {
        const giraId = String(s.giraId || '').trim();
        const fallback = giras.find(g => String(g.giraid) === giraId)?.name || '';
        return {
          giraId,
          name: (s.name || '').trim() || fallback,
          showRankingFinal: s.showRankingFinal !== false,
          showCopas: s.showCopas !== false,
        };
      })
      .filter(s => s.giraId !== '' && (s.showRankingFinal || s.showCopas))
      .slice(0, MAX_SEASONS);

    const payload: HistorialConfig = { editions: cleaned, seasons: cleanedSeasons };
    saveSiteConfig.mutate(
      { password: getSuperAdminPassword(), historial_config: payload },
      {
        onSuccess: () => {
          setRows(cleaned);
          setSeasons(cleanedSeasons);
          toast({
            title: 'Historial guardado',
            description: `${cleaned.length} edición(es) y ${cleanedSeasons.length} temporada(s) configuradas.`,
          });
        },
        onError: (err) =>
          toast({ title: 'Error al guardar', description: err.message, variant: 'destructive' }),
      },
    );
  };

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <History className="h-5 w-5 text-primary" />
            Historial de Resultados
          </CardTitle>
          <CardDescription>
            Registra hasta {MAX_EDITIONS} años anteriores con su torneo_id. En la página
            /historial el usuario elige el año y ve los resultados de ese torneo.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {isLoading ? (
            <div className="flex justify-center py-8">
              <Loader2 className="h-6 w-6 animate-spin text-primary" />
            </div>
          ) : (
            <>
              <div className="rounded-md border border-border overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-28">Año</TableHead>
                      <TableHead className="w-36">torneo_id</TableHead>
                      <TableHead>Etiqueta (opcional)</TableHead>
                      <TableHead className="w-16 text-right">—</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {rows.length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={4} className="text-center text-muted-foreground py-6">
                          Sin años configurados. Agrega el primero.
                        </TableCell>
                      </TableRow>
                    ) : (
                      rows.map((row, idx) => (
                        <TableRow key={idx}>
                          <TableCell>
                            <Input
                              type="number"
                              inputMode="numeric"
                              value={row.year || ''}
                              onChange={(e) => updateRow(idx, { year: Number(e.target.value) })}
                              placeholder="2025"
                            />
                          </TableCell>
                          <TableCell>
                            <Input
                              value={row.torneoId || ''}
                              onChange={(e) => updateRow(idx, { torneoId: e.target.value.replace(/\D/g, '') })}
                              placeholder="354"
                            />
                          </TableCell>
                          <TableCell>
                            <Input
                              value={row.label || ''}
                              onChange={(e) => updateRow(idx, { label: e.target.value })}
                              placeholder="LV Torneo Anual 2025"
                            />
                          </TableCell>
                          <TableCell className="text-right">
                            <Button variant="ghost" size="icon" onClick={() => removeRow(idx)}>
                              <Trash2 className="h-4 w-4 text-destructive" />
                            </Button>
                          </TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </div>

              <Button
                variant="outline"
                onClick={addRow}
                disabled={rows.length >= MAX_EDITIONS}
                className="gap-2"
              >
                <Plus className="h-4 w-4" />
                Agregar año
              </Button>
            </>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Trophy className="h-5 w-5 text-primary" />
            Temporadas / Giras publicadas
          </CardTitle>
          <CardDescription>
            Publica por temporada (gira) el <strong>Ranking Final</strong> y las <strong>Copas</strong>.
            Ejemplo: “Gira 2025-2026” → Ranking Final y Copas.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {isLoading ? (
            <div className="flex justify-center py-8">
              <Loader2 className="h-6 w-6 animate-spin text-primary" />
            </div>
          ) : (
            <>
              <div className="rounded-md border border-border overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-44">Gira</TableHead>
                      <TableHead>Nombre visible</TableHead>
                      <TableHead className="w-32 text-center">Ranking Final</TableHead>
                      <TableHead className="w-28 text-center">Copas</TableHead>
                      <TableHead className="w-16 text-right">—</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {seasons.length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={5} className="text-center text-muted-foreground py-6">
                          Sin temporadas publicadas. Agrega la primera.
                        </TableCell>
                      </TableRow>
                    ) : (
                      seasons.map((season, idx) => (
                        <TableRow key={idx}>
                          <TableCell>
                            <Input
                              list={`historial-giras-${idx}`}
                              value={season.giraId || ''}
                              onChange={(e) => {
                                const giraId = e.target.value.replace(/\D/g, '');
                                const match = giras.find(g => String(g.giraid) === giraId);
                                updateSeason(idx, {
                                  giraId,
                                  name: season.name?.trim() ? season.name : (match?.name || ''),
                                });
                              }}
                              placeholder="17"
                            />
                            <datalist id={`historial-giras-${idx}`}>
                              {giras.map(g => (
                                <option key={g.giraid} value={String(g.giraid)}>
                                  {g.name}
                                </option>
                              ))}
                            </datalist>
                          </TableCell>
                          <TableCell>
                            <Input
                              value={season.name || ''}
                              onChange={(e) => updateSeason(idx, { name: e.target.value })}
                              placeholder="Gira 2025-2026"
                            />
                          </TableCell>
                          <TableCell className="text-center">
                            <Switch
                              checked={season.showRankingFinal !== false}
                              onCheckedChange={(v) => updateSeason(idx, { showRankingFinal: v })}
                            />
                          </TableCell>
                          <TableCell className="text-center">
                            <Switch
                              checked={season.showCopas !== false}
                              onCheckedChange={(v) => updateSeason(idx, { showCopas: v })}
                            />
                          </TableCell>
                          <TableCell className="text-right">
                            <Button variant="ghost" size="icon" onClick={() => removeSeason(idx)}>
                              <Trash2 className="h-4 w-4 text-destructive" />
                            </Button>
                          </TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </div>

              <div className="flex flex-wrap gap-2">
                <Button
                  variant="outline"
                  onClick={addSeason}
                  disabled={seasons.length >= MAX_SEASONS}
                  className="gap-2"
                >
                  <Plus className="h-4 w-4" />
                  Agregar temporada
                </Button>
                <Button onClick={handleSave} disabled={saveSiteConfig.isPending} className="gap-2">
                  {saveSiteConfig.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                  Guardar historial
                </Button>
              </div>
              <p className="text-xs text-muted-foreground">
                Recuerda activar la página <strong>HISTORIAL</strong> en Admin &gt; Config para que
                aparezca en el menú público. El botón Guardar guarda los años y las temporadas.
              </p>
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default AdminHistorial;
