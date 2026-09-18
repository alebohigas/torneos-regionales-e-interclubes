/**
 * AdminGira
 *
 * Configuración de la GIRA activa del dominio (`site_config.giraid`) y del
 * TORNEO específico dentro de esa gira (`site_config.torneoid`).
 *
 * - Selector con todas las giras disponibles (`/api/gira.php`).
 * - Si la gira elegida tiene `uso = 0`, se muestra una alerta debajo del campo.
 * - Muestra las copas de la gira y cómo resuelve su `grupocopas`.
 * - Debajo de las copas, un selector con los torneos de la gira configurada:
 *   define el torneo que alimenta convocatoria, reglas, fechas, salidas y
 *   jugadores. `/historial` sigue consultando toda la gira.
 */

import { useEffect, useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { AlertTriangle, CheckCircle2, Loader2, Route, Trophy } from 'lucide-react';
import { useToast } from '@/hooks/use-toast';
import { useSiteConfig, useSaveSiteConfig, type SiteConfigSaveError } from '@/hooks/useSiteConfig';
import { useGiraId } from '@/hooks/useGiraId';
import { useTorneoId } from '@/hooks/useTorneoId';
import { useGiraInfo, useGirasList } from '@/hooks/useGiraData';
import { getSuperAdminPassword } from '@/lib/superAdminAuth';

const AdminGira = () => {
  const { toast } = useToast();
  const { data: siteConfig, isLoading } = useSiteConfig();
  const { giraId, setGiraId } = useGiraId();
  const { torneoId, setTorneoId } = useTorneoId();
  const { data: giras } = useGirasList();
  const { data: gira } = useGiraInfo();
  const saveSiteConfig = useSaveSiteConfig();

  const [input, setInput] = useState(giraId);
  const [torneoInput, setTorneoInput] = useState(torneoId);

  /** Refleja el valor del servidor cuando resuelve. */
  useEffect(() => {
    if (siteConfig?.giraid) setInput(String(siteConfig.giraid));
  }, [siteConfig?.giraid]);

  useEffect(() => {
    if (siteConfig?.torneoid) setTorneoInput(String(siteConfig.torneoid));
  }, [siteConfig?.torneoid]);

  /** Gira seleccionada en el campo (aunque aún no se guarde). */
  const selected = giras?.find((g) => String(g.giraid) === String(input).trim());
  const selectedFinished = selected ? selected.uso === 0 : gira?.uso === 0;

  const torneos = gira?.torneos ?? [];
  const selectedTorneo = torneos.find((t) => String(t.id) === String(torneoInput).trim());

  const handleSave = () => {
    const value = parseInt(input, 10);
    if (!Number.isFinite(value)) return;
    saveSiteConfig.mutate(
      { giraid: value, password: getSuperAdminPassword() },
      {
        onSuccess: (res) => {
          const confirmed = res?.giraid;
          if (confirmed == null || Number(confirmed) !== value) {
            toast({
              title: 'La gira no quedó guardada',
              description: `El servidor devolvió ${confirmed ?? 'un valor vacío'} en lugar de ${value}.`,
              variant: 'destructive',
            });
            return;
          }
          setGiraId(String(confirmed));
          toast({
            title: 'Gira configurada',
            description: `La gira ${value} aplica para todos los visitantes de este dominio.`,
          });
        },
        onError: (err: SiteConfigSaveError) => {
          toast({
            title: 'Error al guardar en servidor',
            description: `${err?.message ?? err}. No se modificó la gira activa.`,
            variant: 'destructive',
          });
        },
      }
    );
  };

  const handleSaveTorneo = () => {
    const raw = String(torneoInput).trim();
    const value = raw === '' ? null : parseInt(raw, 10);
    if (value !== null && !Number.isFinite(value)) return;
    saveSiteConfig.mutate(
      { torneoid: value, password: getSuperAdminPassword() },
      {
        onSuccess: (res) => {
          const confirmed = res?.torneoid ?? null;
          if ((confirmed ?? null) !== (value ?? null)) {
            toast({
              title: 'El torneo no quedó guardado',
              description: `El servidor devolvió ${confirmed ?? 'un valor vacío'} en lugar de ${value ?? 'vacío'}.`,
              variant: 'destructive',
            });
            return;
          }
          setTorneoId(confirmed == null ? '' : String(confirmed));
          toast({
            title: value == null ? 'Torneo sin configurar' : 'Torneo configurado',
            description:
              value == null
                ? 'Las páginas por torneo quedarán sin datos hasta elegir uno.'
                : `El torneo ${value} alimenta convocatoria, reglas, fechas, salidas y jugadores.`,
          });
        },
        onError: (err: SiteConfigSaveError) => {
          toast({
            title: 'Error al guardar en servidor',
            description: `${err?.message ?? err}. No se modificó el torneo activo.`,
            variant: 'destructive',
          });
        },
      }
    );
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Route className="h-5 w-5 text-primary" />
          Gira activa (Global)
        </CardTitle>
        <CardDescription>
          La gira es la base de toda la información del sitio: agrupa las copas y los torneos
          que se despliegan en las páginas públicas.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {isLoading ? (
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin" />
            Cargando configuración del servidor...
          </div>
        ) : siteConfig?.giraid ? (
          <p className="text-sm text-muted-foreground">
            Gira en servidor: <span className="font-mono font-bold">{siteConfig.giraid}</span>{' '}
            {gira?.name && <span className="font-semibold">— {gira.name}</span>}
          </p>
        ) : (
          <p className="flex items-center gap-1 text-sm text-amber-600 dark:text-amber-400">
            <AlertTriangle className="h-4 w-4" />
            Sin gira configurada. Las páginas no mostrarán información hasta configurarla.
          </p>
        )}

        <div className="max-w-md space-y-2">
          <Label htmlFor="giraid">Gira ID</Label>
          <div className="flex gap-2">
            <Input
              id="giraid"
              list="giras-disponibles"
              value={input}
              onChange={(e) => setInput(e.target.value)}
              placeholder="Ej: 19"
              className="font-mono"
            />
            <datalist id="giras-disponibles">
              {giras?.map((g) => (
                <option key={g.giraid} value={String(g.giraid)}>
                  {g.name} {g.uso === 0 ? '(terminada)' : ''}
                </option>
              ))}
            </datalist>
            <Button onClick={handleSave} disabled={!input || saveSiteConfig.isPending}>
              {saveSiteConfig.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Guardar'}
            </Button>
          </div>

          {selected && (
            <p className="text-sm text-muted-foreground">
              Seleccionada: <span className="font-semibold">{selected.name}</span>
            </p>
          )}

          {/* Alerta cuando la gira elegida ya terminó (uso = 0) */}
          {selectedFinished && (
            <Alert variant="destructive">
              <AlertTriangle className="h-4 w-4" />
              <AlertTitle>Copa terminada (uso = 0)</AlertTitle>
              <AlertDescription>
                Esta gira está marcada como terminada en la base de datos. El sitio público
                mostrará su nombre con el aviso “COPA TERMINADA”. Cambia `gira.uso` a 1 para
                reactivarla.
              </AlertDescription>
            </Alert>
          )}

          {giraId && !selectedFinished && (
            <p className="flex items-center gap-1 text-sm text-muted-foreground">
              <CheckCircle2 className="h-4 w-4 text-primary" />
              Gira local: <span className="font-mono font-bold">{giraId}</span>
            </p>
          )}
        </div>

        {/* Copas de la gira y su grupocopas resuelto */}
        {gira?.copas?.length ? (
          <div className="space-y-2">
            <p className="text-sm font-semibold">Copas de la gira</p>
            <div className="space-y-1">
              {gira.copas.map((c) => (
                <div key={c.copasid} className="flex flex-wrap items-center gap-2 text-sm">
                  <span className="font-mono text-muted-foreground">#{c.copasid}</span>
                  <span className="font-medium">{c.name}</span>
                  <Badge variant={c.isConsolidated ? 'default' : 'secondary'}>
                    {c.isConsolidated ? `consolida ${c.group.join(', ')}` : 'información propia'}
                  </Badge>
                </div>
              ))}
            </div>
            {gira.torneos?.length ? (
              <p className="text-xs text-muted-foreground">
                {gira.torneos.length} torneo(s) ligados a esta gira.
              </p>
            ) : null}
          </div>
        ) : null}

        {/* ===== Torneo específico dentro de la gira ===== */}
        <div className="space-y-3 border-t pt-4">
          <div>
            <p className="flex items-center gap-2 text-sm font-semibold">
              <Trophy className="h-4 w-4 text-primary" />
              Torneo activo (dentro de la gira)
            </p>
            <p className="text-sm text-muted-foreground">
              Define el torneo que alimenta convocatoria, reglas, fechas, salidas y jugadores.
              El historial sigue mostrando la información de todos los torneos de la gira.
            </p>
          </div>

          {siteConfig?.torneoid ? (
            <p className="text-sm text-muted-foreground">
              Torneo en servidor: <span className="font-mono font-bold">{siteConfig.torneoid}</span>
            </p>
          ) : (
            <p className="flex items-center gap-1 text-sm text-amber-600 dark:text-amber-400">
              <AlertTriangle className="h-4 w-4" />
              Sin torneo configurado.
            </p>
          )}

          {!giraId ? (
            <p className="text-sm text-muted-foreground">
              Guarda primero la gira para ver sus torneos disponibles.
            </p>
          ) : (
            <div className="max-w-md space-y-2">
              <Label htmlFor="torneoid">Torneo ID</Label>
              <div className="flex gap-2">
                <select
                  id="torneoid"
                  value={String(torneoInput ?? '')}
                  onChange={(e) => setTorneoInput(e.target.value)}
                  className="h-10 flex-1 rounded-md border border-input bg-background px-3 text-sm font-mono"
                >
                  <option value="">— Sin torneo —</option>
                  {torneos.map((t) => (
                    <option key={t.id} value={String(t.id)}>
                      {t.id} — {t.name}
                      {t.status ? ` (${t.status})` : ''}
                    </option>
                  ))}
                </select>
                <Button onClick={handleSaveTorneo} disabled={saveSiteConfig.isPending}>
                  {saveSiteConfig.isPending ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    'Guardar'
                  )}
                </Button>
              </div>

              {!torneos.length && (
                <p className="text-sm text-muted-foreground">
                  Esta gira no tiene torneos registrados.
                </p>
              )}

              {selectedTorneo && (
                <p className="text-sm text-muted-foreground">
                  Seleccionado: <span className="font-semibold">{selectedTorneo.name}</span>
                </p>
              )}

              {torneoId && (
                <p className="flex items-center gap-1 text-sm text-muted-foreground">
                  <CheckCircle2 className="h-4 w-4 text-primary" />
                  Torneo local: <span className="font-mono font-bold">{torneoId}</span>
                </p>
              )}
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
};

export default AdminGira;
