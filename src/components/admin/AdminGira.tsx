/**
 * AdminGira
 *
 * Configuración de la GIRA activa del dominio (`site_config.giraid`).
 * En este modelo la gira es el eje del sitio: agrupa varias copas
 * (`copas.giraid`) y varios torneos (`torneo.giraid`).
 *
 * - Selector con todas las giras disponibles (`/api/gira.php`).
 * - Si la gira elegida tiene `uso = 0`, se muestra una alerta debajo del campo
 *   (la página pública muestra el nombre con el aviso "COPA TERMINADA").
 * - Muestra las copas de la gira y cómo resuelve su `grupocopas`, para
 *   verificar de un vistazo que la información consolidada es la correcta.
 */

import { useEffect, useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { AlertTriangle, CheckCircle2, Loader2, Route } from 'lucide-react';
import { useToast } from '@/hooks/use-toast';
import { useSiteConfig, useSaveSiteConfig } from '@/hooks/useSiteConfig';
import { useGiraId } from '@/hooks/useGiraId';
import { useGiraInfo, useGirasList } from '@/hooks/useGiraData';
import { getSuperAdminPassword } from '@/lib/superAdminAuth';

const AdminGira = () => {
  const { toast } = useToast();
  const { data: siteConfig, isLoading } = useSiteConfig();
  const { giraId, setGiraId } = useGiraId();
  const { data: giras } = useGirasList();
  const { data: gira } = useGiraInfo();
  const saveSiteConfig = useSaveSiteConfig();

  const [input, setInput] = useState(giraId);

  /** Refleja el valor del servidor cuando resuelve. */
  useEffect(() => {
    if (siteConfig?.giraid) setInput(String(siteConfig.giraid));
  }, [siteConfig?.giraid]);

  /** Gira seleccionada en el campo (aunque aún no se guarde). */
  const selected = giras?.find((g) => String(g.giraid) === String(input).trim());
  const selectedFinished = selected ? selected.uso === 0 : gira?.uso === 0;

  const handleSave = () => {
    const value = parseInt(input, 10);
    if (!Number.isFinite(value)) return;
    setGiraId(String(value));
    saveSiteConfig.mutate(
      { giraid: value, password: getSuperAdminPassword() },
      {
        onSuccess: () =>
          toast({
            title: 'Gira configurada',
            description: `La gira ${value} aplica para todos los visitantes de este dominio.`,
          }),
        onError: (err: any) =>
          toast({
            title: 'Error al guardar en servidor',
            description: `${err?.message ?? err}. Se guardó solo localmente.`,
            variant: 'destructive',
          }),
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
      </CardContent>
    </Card>
  );
};

export default AdminGira;
