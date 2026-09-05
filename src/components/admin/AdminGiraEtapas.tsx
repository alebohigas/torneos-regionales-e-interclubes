/**
 * AdminGiraEtapas
 *
 * Visibilidad de las ETAPAS (torneos) de la gira activa.
 * Cada torneo ligado al `giraid` aparece con un switch; los apagados se
 * guardan en `site_config.gira_config.hiddenTorneos` y desaparecen del menú
 * GIRA y de las subpáginas /jugadores/e/:etapa y /resultados/e/:etapa.
 */

import { useEffect, useMemo, useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Eye, EyeOff, Loader2, ListOrdered } from 'lucide-react';
import { useToast } from '@/hooks/use-toast';
import { useSiteConfig, useSaveSiteConfig } from '@/hooks/useSiteConfig';
import { useJugadoresEtapas } from '@/hooks/useJugadoresEtapas';
import { formatEtapaLabel } from '@/lib/etapaLabel';
import { getSuperAdminPassword } from '@/lib/superAdminAuth';

const AdminGiraEtapas = () => {
  const { toast } = useToast();
  const { data: siteConfig } = useSiteConfig();
  /** Todas las etapas de la gira, incluidas las ocultas (showHidden). */
  const { data: etapas = [], isLoading } = useJugadoresEtapas({ includeHidden: true });
  const saveSiteConfig = useSaveSiteConfig();

  const serverHidden = useMemo(
    () => (siteConfig?.gira_config?.hiddenTorneos ?? []).map(Number),
    [siteConfig?.gira_config?.hiddenTorneos]
  );
  const [hidden, setHidden] = useState<number[]>(serverHidden);

  useEffect(() => setHidden(serverHidden), [serverHidden]);

  const toggle = (torneoid: number, visible: boolean) => {
    setHidden((prev) =>
      visible ? prev.filter((id) => id !== torneoid) : Array.from(new Set([...prev, torneoid]))
    );
  };

  const handleSave = () => {
    saveSiteConfig.mutate(
      { gira_config: { hiddenTorneos: hidden }, password: getSuperAdminPassword() },
      {
        onSuccess: () =>
          toast({
            title: 'Etapas actualizadas',
            description: hidden.length
              ? `${hidden.length} etapa(s) oculta(s) en el sitio público.`
              : 'Todas las etapas están visibles.',
          }),
        onError: (err: any) =>
          toast({
            title: 'No se pudo guardar',
            description: String(err?.message ?? err),
            variant: 'destructive',
          }),
      }
    );
  };

  const dirty = JSON.stringify([...hidden].sort()) !== JSON.stringify([...serverHidden].sort());

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <ListOrdered className="h-5 w-5 text-primary" />
          Etapas de la gira (mostrar / ocultar)
        </CardTitle>
        <CardDescription>
          Apaga una etapa para que no aparezca en el menú GIRA ni en sus páginas de jugadores y
          resultados. El nombre y el orden se toman de la base de datos.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {isLoading ? (
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin" />
            Cargando etapas...
          </div>
        ) : etapas.length === 0 ? (
          <p className="text-sm text-muted-foreground">
            Esta gira todavía no tiene etapas con información registrada.
          </p>
        ) : (
          <div className="divide-y rounded-md border">
            {etapas.map((e) => {
              const isHidden = hidden.includes(e.torneoid);
              return (
                <div key={e.torneoid} className="flex items-center justify-between gap-3 p-3">
                  <div className="min-w-0">
                    <p className="flex items-center gap-2 font-semibold">
                      {isHidden ? (
                        <EyeOff className="h-4 w-4 text-muted-foreground" />
                      ) : (
                        <Eye className="h-4 w-4 text-primary" />
                      )}
                      {formatEtapaLabel(e.etapaLabel, e.etapa)}
                      <Badge variant="secondary" className="font-mono">
                        #{e.torneoid}
                      </Badge>
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                      {e.name} · {e.playerCount} jugador(es)
                    </p>
                  </div>
                  <Switch
                    checked={!isHidden}
                    onCheckedChange={(v) => toggle(e.torneoid, v)}
                    aria-label={`Mostrar ${e.name}`}
                  />
                </div>
              );
            })}
          </div>
        )}

        <Button onClick={handleSave} disabled={!dirty || saveSiteConfig.isPending}>
          {saveSiteConfig.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Guardar cambios'}
        </Button>
      </CardContent>
    </Card>
  );
};

export default AdminGiraEtapas;
