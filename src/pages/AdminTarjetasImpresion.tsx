import { useEffect, useMemo, useState, type CSSProperties, type ReactNode } from 'react';
import { Navigate, useNavigate, useSearchParams } from 'react-router-dom';
import { ArrowLeft, Loader2, Printer, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { usePageVisibility } from '@/contexts/PageVisibilityContext';
import { useStaffAuth } from '@/contexts/StaffAuthContext';
import { apiFetch } from '@/lib/apiClient';
import { getAleinTarjetasUrl } from '@/config/api';
import '@/styles/alein-print.css';

interface AleinFilter {
  date: string;
  courseId: string;
  course: string;
  categoryId: string;
  category: string;
  shortName: string;
  system: string;
}

interface AleinHole { number: number; yards: number; par: number; parTime: string }
interface AleinCard {
  id: string;
  folio: string;
  playerNumber: string;
  playerName: string;
  club: string;
  category: string;
  tee: string;
  teeColor: string;
  startHole: number;
  startTime: string;
  system: string;
  course: string;
  date: string;
  holes: AleinHole[];
}

interface AleinResponse {
  tournament: { id: string; name: string; logo: string };
  filters: AleinFilter[];
  cards: AleinCard[];
}

const dateLabel = (value: string): string => {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
  const [year, month, day] = value.split('-').map(Number);
  return new Intl.DateTimeFormat('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
    .format(new Date(year, month - 1, day));
};

const chunkCards = (cards: AleinCard[]): (AleinCard | null)[][] => {
  if (!cards.length) return [];
  const sheets: (AleinCard | null)[][] = [];
  for (let i = 0; i < cards.length; i += 3) {
    const page: (AleinCard | null)[] = cards.slice(i, i + 3);
    while (page.length < 3) page.push(null);
    sheets.push(page);
  }
  return sheets;
};

const sumHoles = (holes: AleinHole[], key: 'yards' | 'par'): number =>
  holes.reduce((total, hole) => total + (hole[key] || 0), 0);

// Solo se muestran los hoyos que tienen distancia (yardaje > 0); si ninguno tiene, se muestran todos.
const activeHoles = (holes: AleinHole[]): AleinHole[] => {
  const withYards = holes.filter(hole => hole.yards > 0);
  return withYards.length ? withYards : holes;
};

interface PrintSettings {
  headerMm: number;
  sideMarginMm: number;
  scale: number;
  rowHeightMm: number;
  scoreTableFontPt: number;
  paddingTopMm: number;
  paddingBottomMm: number;
  holeFontPt: number;
  categoryFontPt: number;
  playerFontPt: number;
}

const DEFAULT_PRINT_SETTINGS: PrintSettings = {
  headerMm: 14,
  sideMarginMm: 7,
  scale: 100,
  rowHeightMm: 6.2,
  scoreTableFontPt: 8,
  paddingTopMm: 2,
  paddingBottomMm: 1,
  holeFontPt: 11,
  categoryFontPt: 10,
  playerFontPt: 10,
};

const cleanTeeColor = (tee: string, teeColor: string): string => {
  const isHex = (value: string) => /^#(?:[\da-f]{3}|[\da-f]{6}|[\da-f]{8})$/i.test(value.trim());
  if (tee && !isHex(tee)) return tee;
  if (teeColor && !isHex(teeColor)) return teeColor;
  return 'TEE';
};

const Scorecard = ({ card, tournament, holeRange }: { card: AleinCard; tournament: AleinResponse['tournament']; holeRange: [number, number] }) => {
  const ranged = card.holes.filter(hole => hole.number >= holeRange[0] && hole.number <= holeRange[1]);
  const holes = activeHoles(ranged.length ? ranged : card.holes);
  const front = holes.length <= 9 ? holes : holes.filter(hole => hole.number <= 9);
  const back = holes.length <= 9 ? [] : holes.filter(hole => hole.number > 9);
  const frontYards = sumHoles(front, 'yards');
  const backYards = sumHoles(back, 'yards');
  const frontPar = sumHoles(front, 'par');
  const backPar = sumHoles(back, 'par');
  const hasBackNine = back.length > 0;
  // Ronda de 9 hoyos: sin columna V1, solo TOTAL.
  const isNineHole = holes.length <= 9;
  const frontSum = isNineHole ? null : <td className="alein-sum" />;
  const splitCells = (render: (hole: AleinHole) => ReactNode) => (
    <>
      {front.map(render)}
      {frontSum}
      {back.map(render)}
      {hasBackNine && <td className="alein-sum" />}
      <td className="alein-sum" />
    </>
  );

  return (
    <article className="alein-scorecard">
      <header className="alein-card-head">
        <div>{tournament.logo && <img className="alein-card-logo" src={tournament.logo} alt="" />}</div>
        <div className="alein-card-title">
          <strong>{tournament.name}</strong>
          <span>{card.course} · {dateLabel(card.date)}</span>
        </div>
      </header>

      <section className="alein-player-grid">
        <div className="alein-start"><strong>H{String(card.startHole).padStart(2, '0')}</strong><span>{card.startTime}</span></div>
        <div className="alein-player"><strong>{card.playerNumber} {card.playerName}</strong><span>{card.club || 'CLUB NO REGISTRADO'}</span></div>
        <div className="alein-category"><strong>{card.category}</strong><span>{cleanTeeColor(card.tee, card.teeColor)}</span></div>
      </section>

      <table className="alein-score-table" aria-label={`Tarjeta de ${card.playerName}`}>
        <tbody>
          <tr className="alein-compact-row">
            <th className="alein-row-label">HOYO</th>
            {front.map(h => <th key={h.number} className={h.number === card.startHole ? 'alein-hole-start alein-hole-num' : 'alein-hole-num'}>{h.number}</th>)}
            {!isNineHole && <th className="alein-sum">V1</th>}
            {back.map(h => <th key={h.number} className={h.number === card.startHole ? 'alein-hole-start alein-hole-num' : 'alein-hole-num'}>{h.number}</th>)}
            {hasBackNine && <th className="alein-sum">V2</th>}<th className="alein-sum">TOTAL</th>
          </tr>
          <tr className="alein-compact-row">
            <th className="alein-row-label">YARDAS</th>
            {front.map(h => <td key={h.number}>{h.yards || ''}</td>)}
            {!isNineHole && <td className="alein-sum">{frontYards || ''}</td>}
            {back.map(h => <td key={h.number}>{h.yards || ''}</td>)}
            {hasBackNine && <td className="alein-sum">{backYards || ''}</td>}<td className="alein-sum">{frontYards + backYards || ''}</td>
          </tr>
          <tr className="alein-compact-row">
            <th className="alein-row-label">PAR</th>
            {front.map(h => <td key={h.number}>{h.par || ''}</td>)}
            {!isNineHole && <td className="alein-sum">{frontPar || ''}</td>}
            {back.map(h => <td key={h.number}>{h.par || ''}</td>)}
            {hasBackNine && <td className="alein-sum">{backPar || ''}</td>}<td className="alein-sum">{frontPar + backPar || ''}</td>
          </tr>
          <tr><th className="alein-row-label">PAR TIME</th>{splitCells(h => <td key={h.number}>{h.parTime}</td>)}</tr>
          <tr>
            <th className="alein-row-label alein-gross-cell">SCORE<br />GROSS</th>
            {front.map(h => <td key={h.number} className="alein-gross-cell" />)}
            {!isNineHole && <td className="alein-sum alein-gross-cell" />}
            {back.map(h => <td key={h.number} className="alein-gross-cell" />)}
            {hasBackNine && <td className="alein-sum alein-gross-cell" />}<td className="alein-sum alein-gross-cell" />
          </tr>
        </tbody>
      </table>

      <footer className="alein-signatures">
        <div><span>SISTEMA</span><strong>{card.system || '—'}</strong></div>
        <div className="alein-sign-line">ANOTADOR</div>
        <div className="alein-sign-line">{card.playerName}</div>
        <div className="alein-folio">FOLIO {card.folio}</div>
      </footer>
      <table className="alein-marker-table" aria-label="Score del anotador">
        <tbody><tr><th>SCORE<br />ANOTADOR</th>{front.map(h => <td key={h.number}>{h.number}</td>)}{!isNineHole && <th>V1</th>}{back.map(h => <td key={h.number}>{h.number}</td>)}{hasBackNine && <th>V2</th>}<th>TOTAL</th></tr></tbody>
      </table>
    </article>
  );
};

const AdminTarjetasImpresion = () => {
  const { isAdmin } = usePageVisibility();
  const { session: staffSession, hasArea } = useStaffAuth();
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const [data, setData] = useState<AleinResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const date = params.get('fecha') || '';
  const courseId = params.get('campoid') || '';
  const categoryId = params.get('catid') || '';
  const system = params.get('sistema') || '';
  const endDate = params.get('hasta') || '';
  const [printSettings, setPrintSettings] = useState<PrintSettings>(DEFAULT_PRINT_SETTINGS);

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      setData(await apiFetch<AleinResponse>(getAleinTarjetasUrl({
        date,
        endDate,
        courseId,
        categoryId,
        system,
        staffToken: staffSession?.token,
      })));
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : 'No se pudieron cargar las tarjetas.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { void load(); }, [date, endDate, courseId, categoryId, system, staffSession?.token]);

  const allFilters = data?.filters ?? [];
  const dates = useMemo(() => Array.from(new Set(allFilters.map(item => item.date))), [allFilters]);
  const endDates = useMemo(() => dates.filter(value => !date || value >= date), [dates, date]);
  const courses = useMemo(() => {
    const matches = allFilters.filter(item => !date || item.date === date);
    return Array.from(new Map(matches.map(item => [item.courseId, item.course])).entries());
  }, [allFilters, date]);
  const categories = useMemo(() => allFilters.filter(item => (!date || item.date === date) && (!courseId || item.courseId === courseId)), [allFilters, date, courseId]);
  const sheets = useMemo(() => chunkCards(data?.cards ?? []), [data?.cards]);
  const printStyle = useMemo(() => ({
    '--alein-header-height': `${printSettings.headerMm}mm`,
    '--alein-side-margin': `${printSettings.sideMarginMm}mm`,
    '--alein-scale': String(printSettings.scale / 100),
    '--alein-row-height': `${printSettings.rowHeightMm}mm`,
    '--alein-score-table-font': `${printSettings.scoreTableFontPt}pt`,
    '--alein-padding-top': `${printSettings.paddingTopMm}mm`,
    '--alein-padding-bottom': `${printSettings.paddingBottomMm}mm`,
    '--alein-hole-font': `${printSettings.holeFontPt}pt`,
    '--alein-category-font': `${printSettings.categoryFontPt}pt`,
    '--alein-player-font': `${printSettings.playerFontPt}pt`,
  } as CSSProperties), [printSettings]);

  const updatePrintSetting = (key: keyof PrintSettings, value: string, min: number, max: number) => {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return;
    setPrintSettings(current => ({ ...current, [key]: Math.min(max, Math.max(min, parsed)) }));
  };

  const update = (changes: Record<string, string>) => {
    const next = new URLSearchParams(params);
    Object.entries(changes).forEach(([key, value]) => value ? next.set(key, value) : next.delete(key));
    setParams(next);
  };

  if (!isAdmin && !hasArea('alien-system')) return <Navigate to="/admin" replace />;

  return (
    <main className="alein-print-page" style={printStyle}>
      <section className="alein-no-print mx-auto max-w-6xl space-y-5 px-4 py-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="text-sm font-semibold text-primary">ALIEN SYSTEM</p>
            <h1 className="text-2xl font-bold">Tarjetas para impresión</h1>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => navigate('/admin')}><ArrowLeft className="mr-2 h-4 w-4" />Admin</Button>
            <Button variant="outline" onClick={() => void load()} disabled={loading}><RefreshCw className="mr-2 h-4 w-4" />Actualizar</Button>
            <Button onClick={() => window.print()} disabled={!data?.cards.length}><Printer className="mr-2 h-4 w-4" />Imprimir</Button>
          </div>
        </div>

        <Card>
          <CardHeader><CardTitle className="text-lg">Selecciona las tarjetas</CardTitle></CardHeader>
          <CardContent className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div className="space-y-2"><Label>Fecha</Label><Select value={date} onValueChange={value => update({ fecha: value, campoid: '', catid: '', sistema: '' })}><SelectTrigger><SelectValue placeholder="Selecciona" /></SelectTrigger><SelectContent>{dates.map(value => <SelectItem key={value} value={value}>{dateLabel(value)}</SelectItem>)}</SelectContent></Select></div>
            <div className="space-y-2"><Label>Hasta (opcional)</Label><Select value={endDate || 'single'} onValueChange={value => update({ hasta: value === 'single' ? '' : value })} disabled={!date}><SelectTrigger><SelectValue placeholder="Solo ese día" /></SelectTrigger><SelectContent><SelectItem value="single">Solo ese día</SelectItem>{endDates.filter(value => value > date).map(value => <SelectItem key={value} value={value}>{dateLabel(value)}</SelectItem>)}</SelectContent></Select></div>
            <div className="space-y-2"><Label>Campo</Label><Select value={courseId} onValueChange={value => update({ campoid: value, catid: '', sistema: '' })} disabled={!date}><SelectTrigger><SelectValue placeholder="Selecciona" /></SelectTrigger><SelectContent>{courses.map(([id, name]) => <SelectItem key={id} value={id}>{name}</SelectItem>)}</SelectContent></Select></div>
            <div className="space-y-2"><Label>Categoría</Label><Select value={categoryId} onValueChange={value => { const selected = categories.find(item => item.categoryId === value); update({ catid: value, sistema: selected?.system || '' }); }} disabled={!courseId}><SelectTrigger><SelectValue placeholder="Selecciona" /></SelectTrigger><SelectContent>{categories.map(item => <SelectItem key={`${item.date}-${item.courseId}-${item.categoryId}`} value={item.categoryId}>{item.category}</SelectItem>)}</SelectContent></Select></div>
            <div className="space-y-2"><Label>Sistema</Label><Select value={system} onValueChange={value => update({ sistema: value })} disabled={!categoryId}><SelectTrigger><SelectValue placeholder="Automático" /></SelectTrigger><SelectContent>{Array.from(new Set(categories.map(item => item.system).filter(Boolean))).map(value => <SelectItem key={value} value={value}>{value}</SelectItem>)}</SelectContent></Select></div>
            {([
              ['headerMm', 'Cabecera (mm)', 10, 20, 1],
              ['sideMarginMm', 'Margen lateral (mm)', 4, 15, 1],
              ['scale', 'Escala (%)', 85, 105, 1],
              ['rowHeightMm', 'Alto HOYO, PAR, YARDAS y PAR TIME (mm)', 4.5, 8, 0.1],
              ['scoreTableFontPt', 'Letra HOYO, PAR, YARDAS y PAR TIME (pt)', 6, 12, 0.5],
              ['paddingTopMm', 'Padding superior (mm)', 0, 5, 0.5],
              ['paddingBottomMm', 'Padding inferior (mm)', 0, 4, 0.5],
              ['holeFontPt', 'Letra hoyo y hora (pt)', 9, 15.5, 0.5],
              ['categoryFontPt', 'Letra categoría (pt)', 8, 15, 0.5],
              ['playerFontPt', 'Letra jugador (pt)', 8, 14, 0.5],
            ] as const).map(([key, label, min, max, step]) => (
              <div className="space-y-2" key={key}>
                <Label htmlFor={`alein-${key}`}>{label}</Label>
                <Input id={`alein-${key}`} type="number" min={min} max={max} step={step} value={printSettings[key]} onChange={event => updatePrintSetting(key, event.target.value, min, max)} />
              </div>
            ))}
          </CardContent>
        </Card>

        {error && <Alert variant="destructive"><AlertTitle>No se pudieron cargar las tarjetas</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}
        {loading && <div className="flex items-center justify-center py-8 text-muted-foreground"><Loader2 className="mr-2 h-5 w-5 animate-spin" />Cargando…</div>}
        {!loading && date && courseId && categoryId && !data?.cards.length && <Alert><AlertTitle>Sin tarjetas</AlertTitle><AlertDescription>No hay jugadores asignados para esta selección.</AlertDescription></Alert>}
        {!!data?.cards.length && <p className="text-sm text-muted-foreground">{data.cards.length} tarjeta{data.cards.length === 1 ? '' : 's'} · {sheets.length} hoja{sheets.length === 1 ? '' : 's'} tamaño carta.</p>}
      </section>

      {!loading && sheets.length > 0 && (
        <section className="alein-sheet-preview"><div className="alein-sheets">
          {sheets.map((sheet, pageIndex) => (
            <div className="alein-sheet" key={`sheet-${pageIndex}`}>
              {sheet.map((card, slotIndex) => card
                ? <Scorecard key={card.id} card={card} tournament={data?.tournament ?? { id: '', name: '', logo: '' }} />
                : <div className="alein-scorecard alein-empty-slot" key={`empty-${slotIndex}`} />)}
            </div>
          ))}
        </div></section>
      )}
    </main>
  );
};

export default AdminTarjetasImpresion;