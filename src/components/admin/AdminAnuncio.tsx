/**
 * AdminAnuncio
 * -----------------------------------------------------------------------
 * Admin tab that manages the site-wide scrolling announcement ribbon
 * rendered between the header and the sponsor ribbon on every page.
 *
 * Persists to `site_config.anuncio_config`. Read globally by
 * <AnnouncementRibbon /> mounted inside <Layout />.
 */
import { useEffect, useMemo, useState } from 'react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Slider } from '@/components/ui/slider';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
  Loader2,
  CheckCircle2,
  Megaphone,
  Bold,
  Italic,
  Type as TypeIcon,
  CalendarIcon,
} from 'lucide-react';
import {
  useSiteConfig,
  useSaveSiteConfig,
  type AnuncioConfig,
} from '@/hooks/useSiteConfig';
import { useToast } from '@/hooks/use-toast';
import { getSuperAdminPassword } from '@/lib/superAdminAuth';
import { menuConfig } from '@/data/mockData';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

/** Sensible defaults when no config has been saved yet. */
const DEFAULT_ANUNCIO: AnuncioConfig = {
  enabled: false,
  text: '',
  bgColor: '#111827',
  textColor: '#ffffff',
  fontFamily: 'sans',
  fontSize: 16,
  bold: true,
  italic: false,
  speedSeconds: 30,
  paths: ['*'],
  stickyMobile: false,
  stickyTablet: false,
  stickyDesktop: false,
  timerEnabled: false,
  startDate: '',
  startTime: '08:00',
  endDate: '',
  endTime: '20:00',
};

/**
 * Maps preset → CSS stack for the live preview (must mirror the runtime
 * mapping in AnnouncementRibbon).
 */
const PREVIEW_FONT: Record<string, string> = {
  sans: 'ui-sans-serif, system-ui, sans-serif',
  serif: 'ui-serif, Georgia, serif',
  mono: 'ui-monospace, SFMono-Regular, monospace',
  display: '"Playfair Display", Georgia, serif',
};

const parseDateInput = (value?: string) => {
  if (!value) return undefined;
  const [year, month, day] = value.split('-').map(Number);
  if (!year || !month || !day) return undefined;
  return new Date(year, month - 1, day);
};

const formatDateInput = (date?: Date) => {
  if (!date) return '';
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const getTimerStamp = (date?: string, time?: string) => {
  if (!date || !time) return null;
  return `${date}T${time.length === 5 ? `${time}:00` : time}`;
};

const AnuncioDatePicker = ({
  id,
  value,
  onChange,
  placeholder,
}: {
  id: string;
  value?: string;
  onChange: (value: string) => void;
  placeholder: string;
}) => {
  const date = parseDateInput(value);

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          id={id}
          type="button"
          variant="outline"
          className={cn('w-full justify-start text-left font-normal', !date && 'text-muted-foreground')}
        >
          <CalendarIcon className="mr-2 h-4 w-4" />
          {date ? format(date, 'dd/MM/yyyy', { locale: es }) : <span>{placeholder}</span>}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0" align="start">
        <Calendar
          mode="single"
          selected={date}
          onSelect={(selected) => onChange(formatDateInput(selected))}
          initialFocus
          className="p-3 pointer-events-auto"
        />
      </PopoverContent>
    </Popover>
  );
};

/**
 * AdminAnuncio — form + live preview for the scrolling announcement ribbon.
 */
const AdminAnuncio = () => {
  const { toast } = useToast();
  const { data: siteConfig, isLoading } = useSiteConfig();
  const saveSiteConfig = useSaveSiteConfig();
  // ---- Multi-slot state ---------------------------------------------------
  // Up to 3 independent announcement ribbons stacked one on top of the other.
  const [configs, setConfigs] = useState<AnuncioConfig[]>([
    { ...DEFAULT_ANUNCIO },
    { ...DEFAULT_ANUNCIO },
    { ...DEFAULT_ANUNCIO },
  ]);
  const [activeIdx, setActiveIdx] = useState(0);
  const config = configs[activeIdx];
  /** setState wrapper scoped to the active slot. */
  const setConfig: React.Dispatch<React.SetStateAction<AnuncioConfig>> = (updater) => {
    setConfigs((cs) =>
      cs.map((c, i) => {
        if (i !== activeIdx) return c;
        return typeof updater === 'function'
          ? (updater as (p: AnuncioConfig) => AnuncioConfig)(c)
          : updater;
      }),
    );
  };

  // Hydrate from server whenever the fetched config changes. Accepts both
  // legacy single-object payloads and the new array shape.
  useEffect(() => {
    const raw = siteConfig?.anuncio_config;
    if (!raw) return;
    const arr: AnuncioConfig[] = Array.isArray(raw) ? raw : [raw as AnuncioConfig];
    setConfigs([0, 1, 2].map((i) => ({ ...DEFAULT_ANUNCIO, ...(arr[i] || {}) })));
  }, [siteConfig?.anuncio_config]);

  /** Routes the admin can pick from (mirrors public menu). */
  const routeOptions = useMemo(
    () => menuConfig.map((m) => ({ id: m.id, label: m.label, path: m.path })),
    []
  );

  const selectedPaths = config.paths ?? ['*'];
  const showOnAll = selectedPaths.includes('*');

  /** Toggle a single route in the config.paths array. */
  const togglePath = (path: string) => {
    setConfig((c) => {
      const current = (c.paths ?? ['*']).filter((p) => p !== '*');
      const has = current.includes(path);
      const next = has ? current.filter((p) => p !== path) : [...current, path];
      return { ...c, paths: next.length === 0 ? [] : next };
    });
  };

  /** Toggle "show on every page" master. */
  const toggleShowAll = (checked: boolean) => {
    setConfig((c) => ({ ...c, paths: checked ? ['*'] : [] }));
  };

  /** Persist the current config to the server. */
  const handleSave = () => {
    const missingText = configs.find((c) => c.enabled && !c.text.trim());
    if (missingText) {
      toast({ title: 'Falta texto',
        description: 'Cada anuncio activo debe tener un mensaje. Revisa las pestañas.',
        variant: 'destructive' });
      return;
    }
    const missingPaths = configs.find(
      (c) => c.enabled && !(c.paths ?? []).includes('*') && (c.paths ?? []).length === 0,
    );
    if (missingPaths) {
      toast({ title: 'Falta seleccionar páginas',
        description: 'Cada anuncio activo debe tener al menos una página o "Mostrar en todas".',
        variant: 'destructive' });
      return;
    }
    const invalidTimer = configs.find((c) => {
      if (!c.enabled || !c.timerEnabled) return false;
      const startsAt = getTimerStamp(c.startDate, c.startTime);
      const endsAt = getTimerStamp(c.endDate, c.endTime);
      return !startsAt || !endsAt || startsAt > endsAt;
    });
    if (invalidTimer) {
      toast({
        title: 'Revisa el temporizador',
        description: 'Cada anuncio con temporizador necesita fecha/hora de inicio y final válidas.',
        variant: 'destructive',
      });
      return;
    }
    saveSiteConfig.mutate(
      // Send the full array so all 3 slots persist in one request.
      { password: getSuperAdminPassword(), anuncio_config: configs as unknown as AnuncioConfig },
      {
        onSuccess: () =>
          toast({
            title: 'Anuncio guardado',
            description: 'La tira se aplicó a todos los visitantes.',
          }),
        onError: (err) =>
          toast({
            title: 'Error al guardar',
            description: err.message,
            variant: 'destructive',
          }),
      }
    );
  };

  return (
    <div className="space-y-6">
      {/* Slot selector — one tab per anuncio ribbon. Green dot = enabled. */}
      <Tabs value={String(activeIdx)} onValueChange={(v) => setActiveIdx(Number(v))}>
        <TabsList className="grid grid-cols-3 w-full max-w-md">
          {configs.map((c, i) => (
            <TabsTrigger key={i} value={String(i)} className="gap-2">
              Anuncio {i + 1}
              {c.enabled && (
                <span className="inline-block h-2 w-2 rounded-full bg-emerald-500" aria-label="Activo" />
              )}
            </TabsTrigger>
          ))}
        </TabsList>
      </Tabs>
      <p className="text-xs text-muted-foreground">
        Puedes activar hasta 3 anuncios. Si dos o más coinciden en la misma
        página se apilan verticalmente y empujan el contenido hacia abajo.
      </p>

      {/* ===== Live preview ===== */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Megaphone className="h-5 w-5 text-primary" />
            Vista previa
          </CardTitle>
          <CardDescription>
            Así se verá la tira en la parte superior del sitio (arriba del
            ribbon de patrocinadores).
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div
            className="w-full overflow-hidden border-y border-border rounded"
            style={{ backgroundColor: config.bgColor }}
          >
            <div
              className="sponsor-scroll flex whitespace-nowrap py-2"
              style={{ animationDuration: `${Math.max(5, config.speedSeconds)}s` }}
            >
              {[0, 1].map((i) => (
                <span
                  key={i}
                  className="px-4"
                  style={{
                    color: config.textColor,
                    fontFamily: PREVIEW_FONT[config.fontFamily],
                    fontSize: `${config.fontSize}px`,
                    fontWeight: config.bold ? 700 : 500,
                    fontStyle: config.italic ? 'italic' : 'normal',
                    letterSpacing: '0.02em',
                  }}
                >
                  {(config.text || 'Escribe el mensaje del anuncio...')
                    .concat('\u00A0\u00A0\u00A0•\u00A0\u00A0\u00A0')
                    .repeat(6)}
                </span>
              ))}
            </div>
          </div>
        </CardContent>
      </Card>

      {/* ===== Config form ===== */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <TypeIcon className="h-5 w-5 text-primary" />
            Configuración del anuncio
          </CardTitle>
          <CardDescription>
            Activa, edita el texto, y ajusta colores y tipografía. Se muestra
            en todas las páginas del torneo.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {/* Master switch */}
          <div className="flex items-center justify-between rounded-lg border border-border p-3">
            <div>
              <Label className="text-base">Activar tira de anuncios</Label>
              <p className="text-xs text-muted-foreground">
                Cuando esté apagada, no aparece en ninguna página.
              </p>
            </div>
            <Switch
              checked={config.enabled}
              onCheckedChange={(v) => setConfig((c) => ({ ...c, enabled: v }))}
            />
          </div>

          {/* Sticky controls */}
          <div className="space-y-3 rounded-lg border border-border p-3">
            <div>
              <Label className="text-base">Fijar arriba (sticky)</Label>
              <p className="text-xs text-muted-foreground">
                La tira queda pegada debajo del menú al hacer scroll. Actívala por tipo de dispositivo.
              </p>
            </div>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <label className="flex items-center justify-between rounded-md border border-border px-3 py-2">
                <span className="text-sm font-medium">Celular</span>
                <Switch
                  checked={config.stickyMobile === true}
                  onCheckedChange={(v) => setConfig((c) => ({ ...c, stickyMobile: v }))}
                />
              </label>
              <label className="flex items-center justify-between rounded-md border border-border px-3 py-2">
                <span className="text-sm font-medium">Tableta</span>
                <Switch
                  checked={config.stickyTablet === true}
                  onCheckedChange={(v) => setConfig((c) => ({ ...c, stickyTablet: v }))}
                />
              </label>
              <label className="flex items-center justify-between rounded-md border border-border px-3 py-2">
                <span className="text-sm font-medium">Escritorio</span>
                <Switch
                  checked={config.stickyDesktop === true}
                  onCheckedChange={(v) => setConfig((c) => ({ ...c, stickyDesktop: v }))}
                />
              </label>
            </div>
          </div>

          {/* Timer */}
          <div className="space-y-4 rounded-lg border border-border p-3">
            <div className="flex items-start justify-between gap-4">
              <div>
                <Label className="text-base">Temporizador</Label>
                <p className="text-xs text-muted-foreground">
                  El anuncio se publica al llegar la fecha/hora de inicio y se detiene al llegar la fecha/hora final (hora de Ciudad de México).
                </p>
              </div>
              <Switch
                checked={config.timerEnabled === true}
                onCheckedChange={(v) => setConfig((c) => ({ ...c, timerEnabled: v }))}
              />
            </div>
            <div className={cn('grid grid-cols-1 gap-3 md:grid-cols-4', !config.timerEnabled && 'opacity-50')}>
              <div className="space-y-2">
                <Label htmlFor="anuncio-start-date">Fecha inicio</Label>
                <AnuncioDatePicker
                  id="anuncio-start-date"
                  value={config.startDate}
                  onChange={(value) => setConfig((c) => ({ ...c, startDate: value }))}
                  placeholder="dd/mm/aaaa"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="anuncio-start-time">Hora inicio</Label>
                <Input
                  id="anuncio-start-time"
                  type="time"
                  value={config.startTime || '08:00'}
                  onChange={(e) => setConfig((c) => ({ ...c, startTime: e.target.value }))}
                  disabled={!config.timerEnabled}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="anuncio-end-date">Fecha final</Label>
                <AnuncioDatePicker
                  id="anuncio-end-date"
                  value={config.endDate}
                  onChange={(value) => setConfig((c) => ({ ...c, endDate: value }))}
                  placeholder="dd/mm/aaaa"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="anuncio-end-time">Hora final</Label>
                <Input
                  id="anuncio-end-time"
                  type="time"
                  value={config.endTime || '20:00'}
                  onChange={(e) => setConfig((c) => ({ ...c, endTime: e.target.value }))}
                  disabled={!config.timerEnabled}
                />
              </div>
            </div>
            <p className="text-xs text-muted-foreground">
              {config.timerEnabled ? 'Activo solo dentro del rango seleccionado.' : 'Sin temporizador'}
            </p>
          </div>

          {/* Text */}
          <div className="space-y-2">
            <Label htmlFor="anuncio-text">Texto del anuncio</Label>
            <Textarea
              id="anuncio-text"
              value={config.text}
              onChange={(e) => setConfig((c) => ({ ...c, text: e.target.value }))}
              placeholder="Ej: Inscripciones abiertas hasta el 20 de julio. ¡Cupo limitado!"
              rows={3}
            />
          </div>

          {/* Colors */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="anuncio-bg">Color de fondo</Label>
              <div className="flex items-center gap-2">
                <Input
                  id="anuncio-bg"
                  type="color"
                  value={config.bgColor}
                  onChange={(e) => setConfig((c) => ({ ...c, bgColor: e.target.value }))}
                  className="h-10 w-16 p-1"
                />
                <Input
                  type="text"
                  value={config.bgColor}
                  onChange={(e) => setConfig((c) => ({ ...c, bgColor: e.target.value }))}
                  className="font-mono"
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="anuncio-fg">Color del texto</Label>
              <div className="flex items-center gap-2">
                <Input
                  id="anuncio-fg"
                  type="color"
                  value={config.textColor}
                  onChange={(e) => setConfig((c) => ({ ...c, textColor: e.target.value }))}
                  className="h-10 w-16 p-1"
                />
                <Input
                  type="text"
                  value={config.textColor}
                  onChange={(e) => setConfig((c) => ({ ...c, textColor: e.target.value }))}
                  className="font-mono"
                />
              </div>
            </div>
          </div>

          {/* Typography */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label>Fuente</Label>
              <Select
                value={config.fontFamily}
                onValueChange={(v) =>
                  setConfig((c) => ({ ...c, fontFamily: v as AnuncioConfig['fontFamily'] }))
                }
              >
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="sans">Sans-serif (moderna)</SelectItem>
                  <SelectItem value="serif">Serif (clásica)</SelectItem>
                  <SelectItem value="mono">Monoespaciada</SelectItem>
                  <SelectItem value="display">Display (Playfair)</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>Estilo</Label>
              <ToggleGroup
                type="multiple"
                value={[
                  ...(config.bold ? ['bold'] : []),
                  ...(config.italic ? ['italic'] : []),
                ]}
                onValueChange={(vals) =>
                  setConfig((c) => ({
                    ...c,
                    bold: vals.includes('bold'),
                    italic: vals.includes('italic'),
                  }))
                }
                className="justify-start"
              >
                <ToggleGroupItem value="bold" aria-label="Negrita">
                  <Bold className="h-4 w-4" />
                </ToggleGroupItem>
                <ToggleGroupItem value="italic" aria-label="Cursiva">
                  <Italic className="h-4 w-4" />
                </ToggleGroupItem>
              </ToggleGroup>
            </div>
          </div>

          {/* Font size */}
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <Label>Tamaño de letra</Label>
              <span className="text-sm font-mono text-muted-foreground">{config.fontSize}px</span>
            </div>
            <Slider
              min={10}
              max={48}
              step={1}
              value={[config.fontSize]}
              onValueChange={([v]) => setConfig((c) => ({ ...c, fontSize: v }))}
            />
          </div>

          {/* Speed */}
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <Label>Velocidad (menor = más rápido)</Label>
              <span className="text-sm font-mono text-muted-foreground">
                {config.speedSeconds}s por vuelta
              </span>
            </div>
            <Slider
              min={10}
              max={120}
              step={5}
              value={[config.speedSeconds]}
              onValueChange={([v]) => setConfig((c) => ({ ...c, speedSeconds: v }))}
            />
          </div>

          {/* Pages selector */}
          <div className="space-y-2">
            <Label>Páginas donde se mostrará</Label>
            <label className="flex items-center gap-2 text-sm cursor-pointer rounded border border-border px-3 py-2">
              <Checkbox
                checked={showOnAll}
                onCheckedChange={(v) => toggleShowAll(Boolean(v))}
              />
              <span className="font-medium">Mostrar en todas las páginas</span>
            </label>
            <div
              className={`grid grid-cols-2 sm:grid-cols-3 gap-2 rounded-lg border border-border p-3 max-h-64 overflow-auto ${
                showOnAll ? 'opacity-50 pointer-events-none' : ''
              }`}
            >
              {routeOptions.map((opt) => (
                <label
                  key={opt.id}
                  className="flex items-center gap-2 text-sm cursor-pointer hover:bg-muted/50 px-2 py-1 rounded"
                >
                  <Checkbox
                    checked={showOnAll || selectedPaths.includes(opt.path)}
                    onCheckedChange={() => togglePath(opt.path)}
                  />
                  <span className="font-medium">{opt.label}</span>
                  <span className="text-xs text-muted-foreground font-mono ml-auto">
                    {opt.path}
                  </span>
                </label>
              ))}
            </div>
            <p className="text-xs text-muted-foreground">
              Desactiva "Mostrar en todas" para elegir páginas específicas.
            </p>
          </div>

          <Button
            type="button"
            onClick={handleSave}
            disabled={saveSiteConfig.isPending || isLoading}
            className="gap-2"
          >
            {saveSiteConfig.isPending ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <CheckCircle2 className="h-4 w-4" />
            )}
            Guardar anuncio
          </Button>
        </CardContent>
      </Card>
    </div>
  );
};

export default AdminAnuncio;
