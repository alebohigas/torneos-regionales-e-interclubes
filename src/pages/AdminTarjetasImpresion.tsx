import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { Navigate, useNavigate, useSearchParams } from 'react-router-dom';
import { ArrowLeft, Loader2, Printer, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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

const sumHoles = (holes: AleinHole[], start: number, end: number, key: 'yards' | 'par'): number =>
  holes.slice(start, end).reduce((total, hole) => total + (hole[key] || 0), 0);

const Scorecard = ({ card, tournament }: { card: AleinCard; tournament: AleinResponse['tournament'] }) => {
  const frontYards = sumHoles(card.holes, 0, 9, 'yards');
  const backYards = sumHoles(card.holes, 9, 18, 'yards');
  const frontPar = sumHoles(card.holes, 0, 9, 'par');
  const backPar = sumHoles(card.holes, 9, 18, 'par');
  const cells = <T,>(values: T[], render: (value: T, index: number) => ReactNode) => (
    <>{values.slice(0, 9).map(render)}<td className="alein-sum">{''}</td>{values.slice(9, 18).map((value, index) => render(value, index + 9))}<td className="alein-sum">{''}</td><td className="alein-sum">{''}</td></>
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
        <div className="alein-category"><strong>{card.category}</strong><span>{card.tee || 'TEE'}{card.teeColor ? ` · ${card.teeColor}` : ''}</span></div>
      </section>

      <table className="alein-score-table" aria-label={`Tarjeta de ${card.playerName}`}>
        <tbody>
          <tr>
            <th className="alein-row-label">HOYO</th>
            {card.holes.slice(0, 9).map(h => <th key={h.number} className={h.number === card.startHole ? 'alein-hole-start' : ''}>{h.number}</th>)}
            <th className="alein-sum">V1</th>
            {card.holes.slice(9, 18).map(h => <th key={h.number} className={h.number === card.startHole ? 'alein-hole-start' : ''}>{h.number}</th>)}
            <th className="alein-sum">V2</th><th className="alein-sum">TOTAL</th>
          </tr>
          <tr>
            <th className="alein-row-label">YARDAS</th>
            {card.holes.slice(0, 9).map(h => <td key={h.number}>{h.yards || ''}</td>)}
            <td className="alein-sum">{frontYards || ''}</td>
            {card.holes.slice(9, 18).map(h => <td key={h.number}>{h.yards || ''}</td>)}
            <td className="alein-sum">{backYards || ''}</td><td className="alein-sum">{frontYards + backYards || ''}</td>
          </tr>
          <tr><th className="alein-row-label">PAR TIME</th>{cells(card.holes, h => <td key={h.number}>{h.parTime}</td>)}</tr>
          <tr>
            <th className="alein-row-label alein-gross-cell">SCORE GROSS</th>
            {card.holes.map(h => <td key={h.number} className="alein-gross-cell" />).reduce<React.ReactNode[]>((all, cell, index) => {
              all.push(cell);
              if (index === 8) all.push(<td key="v1" className="alein-sum alein-gross-cell" />);
              return all;
            }, [])}
            <td className="alein-sum alein-gross-cell" /><td className="alein-sum alein-gross-cell" />
          </tr>
        </tbody>
      </table>

      <footer className="alein-signatures">
        <div><span>SISTEMA</span><strong>{card.system || '—'}</strong></div>
        <div className="alein-sign-line">ANOTADOR</div>
        <div className="alein-sign-line">JUGADOR</div>
        <div className="alein-folio">FOLIO {card.folio}</div>
      </footer>
      <table className="alein-marker-table" aria-label="Score del anotador">
        <tbody><tr><th>SCORE<br />ANOTADOR</th>{card.holes.slice(0, 9).map(h => <td key={h.number}>{h.number}</td>)}<th>V1</th>{card.holes.slice(9, 18).map(h => <td key={h.number}>{h.number}</td>)}<th>V2</th><th>TOTAL</th></tr></tbody>
      </table>
    </article>
  );
};

const AdminTarjetasImpresion = () => {
  const { isAdmin } = usePageVisibility();
  const { session } = useStaffAuth();
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const [data, setData] = useState<AleinResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const date = params.get('fecha') || '';
  const courseId = params.get('campoid') || '';
  const categoryId = params.get('catid') || '';
  const system = params.get('sistema') || '';

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      setData(await apiFetch<AleinResponse>(getAleinTarjetasUrl({ date, courseId, categoryId, system })));
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : 'No se pudieron cargar las tarjetas.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { void load(); }, [date, courseId, categoryId, system]);

  const allFilters = data?.filters ?? [];
  const dates = useMemo(() => Array.from(new Set(allFilters.map(item => item.date))), [allFilters]);
  const courses = useMemo(() => {
    const matches = allFilters.filter(item => !date || item.date === date);
    return Array.from(new Map(matches.map(item => [item.courseId, item.course])).entries());
  }, [allFilters, date]);
  const categories = useMemo(() => allFilters.filter(item => (!date || item.date === date) && (!courseId || item.courseId === courseId)), [allFilters, date, courseId]);
  const sheets = useMemo(() => chunkCards(data?.cards ?? []), [data?.cards]);

  const update = (changes: Record<string, string>) => {
    const next = new URLSearchParams(params);
    Object.entries(changes).forEach(([key, value]) => value ? next.set(key, value) : next.delete(key));
    setParams(next);
  };

  if (!isAdmin) return <Navigate to="/admin" replace />;

  return (
    <main className="alein-print-page">
      <section className="alein-no-print mx-auto max-w-6xl space-y-5 px-4 py-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="text-sm font-semibold text-primary">ALEIN SYSTEM</p>
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
          <CardContent className="grid gap-4 md:grid-cols-4">
            <div className="space-y-2"><Label>Fecha</Label><Select value={date} onValueChange={value => update({ fecha: value, campoid: '', catid: '', sistema: '' })}><SelectTrigger><SelectValue placeholder="Selecciona" /></SelectTrigger><SelectContent>{dates.map(value => <SelectItem key={value} value={value}>{dateLabel(value)}</SelectItem>)}</SelectContent></Select></div>
            <div className="space-y-2"><Label>Campo</Label><Select value={courseId} onValueChange={value => update({ campoid: value, catid: '', sistema: '' })} disabled={!date}><SelectTrigger><SelectValue placeholder="Selecciona" /></SelectTrigger><SelectContent>{courses.map(([id, name]) => <SelectItem key={id} value={id}>{name}</SelectItem>)}</SelectContent></Select></div>
            <div className="space-y-2"><Label>Categoría</Label><Select value={categoryId} onValueChange={value => { const selected = categories.find(item => item.categoryId === value); update({ catid: value, sistema: selected?.system || '' }); }} disabled={!courseId}><SelectTrigger><SelectValue placeholder="Selecciona" /></SelectTrigger><SelectContent>{categories.map(item => <SelectItem key={`${item.date}-${item.courseId}-${item.categoryId}`} value={item.categoryId}>{item.category}</SelectItem>)}</SelectContent></Select></div>
            <div className="space-y-2"><Label>Sistema</Label><Select value={system} onValueChange={value => update({ sistema: value })} disabled={!categoryId}><SelectTrigger><SelectValue placeholder="Automático" /></SelectTrigger><SelectContent>{Array.from(new Set(categories.map(item => item.system).filter(Boolean))).map(value => <SelectItem key={value} value={value}>{value}</SelectItem>)}</SelectContent></Select></div>
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