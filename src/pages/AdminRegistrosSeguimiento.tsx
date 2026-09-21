/**
 * AdminRegistrosSeguimiento
 * --------------------------------------------------------------------
 * Tablero de seguimiento (solo lectura) de los pre-registros recibidos
 * del torneo activo: jugador, categoría, club y costo.
 * Incluye totales generales y resúmenes por categoría y por club.
 *
 * Auth: misma contraseña del tablero de pre-registros.
 */

import { useEffect, useMemo, useState, type FormEvent } from 'react';
import Layout from '@/components/layout/Layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Loader2, Lock, Shield, RefreshCw, Search, Users, Building2, Wallet } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useToast } from '@/hooks/use-toast';
import { getRegistroListUrl } from '@/config/api';

const SESSION_KEY = 'registros_admin_session';
const REGISTROS_PASSWORD = 'registros2025';

/** Fila mínima usada por el tablero de seguimiento. */
interface SeguimientoRow {
  id: number;
  reg_nombre?: string;
  reg_apellido?: string;
  reg_correo?: string;
  reg_categoria?: string;
  categoria_name?: string;
  reg_club?: string;
  reg_precio_estimado?: number | string | null;
  reg_monto_confirmado?: number | string | null;
  reg_precio_moneda?: string;
  fecharegistro?: string;
  created_at?: string;
  reg_fecha?: string;
}

const toNumber = (v: unknown): number => {
  const n = Number(String(v ?? '').replace(/[^0-9.-]/g, ''));
  return Number.isFinite(n) ? n : 0;
};

const money = (v: number, currency = 'MXN'): string =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency, maximumFractionDigits: 2 }).format(v);

const fullName = (r: SeguimientoRow): string =>
  `${(r.reg_nombre || '').trim()} ${(r.reg_apellido || '').trim()}`.trim() || '—';

const catName = (r: SeguimientoRow): string =>
  (r.categoria_name || r.reg_categoria || 'Sin categoría').trim() || 'Sin categoría';

const clubName = (r: SeguimientoRow): string =>
  (r.reg_club || 'Sin club').trim() || 'Sin club';

const rowCost = (r: SeguimientoRow): number => {
  const confirmed = toNumber(r.reg_monto_confirmado);
  return confirmed > 0 ? confirmed : toNumber(r.reg_precio_estimado);
};

const rowDate = (r: SeguimientoRow): string => {
  const raw = r.fecharegistro || r.created_at || r.reg_fecha || '';
  if (!raw) return '—';
  const m = String(raw).match(/^(\d{4})-(\d{2})-(\d{2})[T ]?(\d{2}:\d{2})?/);
  if (!m) return String(raw);
  return `${m[3]}/${m[2]}/${m[1]}${m[4] ? ` ${m[4]}` : ''}`;
};

// ============= Login =============

const LoginForm = ({ onLogin }: { onLogin: (pwd: string) => boolean }) => {
  const [pwd, setPwd] = useState('');
  const [err, setErr] = useState(false);
  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (!onLogin(pwd)) { setErr(true); setPwd(''); }
  };
  return (
    <div className="min-h-[60vh] flex items-center justify-center">
      <Card className="w-full max-w-md">
        <CardHeader className="text-center">
          <div className="mx-auto w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center mb-4">
            <Shield className="w-8 h-8 text-primary" />
          </div>
          <CardTitle className="text-2xl">Seguimiento de Pre-Registros</CardTitle>
          <CardDescription>Acceso para el equipo de seguimiento</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="pwd">Contraseña</Label>
              <div className="relative">
                <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                  id="pwd" type="password" value={pwd}
                  onChange={e => { setPwd(e.target.value); setErr(false); }}
                  className={cn('pl-10', err && 'border-destructive focus-visible:ring-destructive')}
                  placeholder="Ingresa la contraseña"
                />
              </div>
              {err && <p className="text-sm text-destructive">Contraseña incorrecta</p>}
            </div>
            <Button type="submit" className="w-full">Entrar</Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
};

// ============= Dashboard =============

const SeguimientoDashboard = ({ password }: { password: string }) => {
  const [rows, setRows] = useState<SeguimientoRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [cat, setCat] = useState('all');
  const [club, setClub] = useState('all');
  const { toast } = useToast();

  const refresh = async () => {
    setLoading(true);
    try {
      const res = await fetch(getRegistroListUrl(password));
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || 'Error al cargar');
      setRows(Array.isArray(json.rows) ? json.rows : []);
    } catch (err: any) {
      toast({ title: 'Error', description: err.message, variant: 'destructive' });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { refresh(); /* eslint-disable-next-line */ }, []);

  const currency = rows.find(r => r.reg_precio_moneda)?.reg_precio_moneda || 'MXN';

  const cats = useMemo(
    () => Array.from(new Set(rows.map(catName))).sort((a, b) => a.localeCompare(b, 'es')),
    [rows],
  );
  const clubs = useMemo(
    () => Array.from(new Set(rows.map(clubName))).sort((a, b) => a.localeCompare(b, 'es')),
    [rows],
  );

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return rows.filter(r => {
      if (cat !== 'all' && catName(r) !== cat) return false;
      if (club !== 'all' && clubName(r) !== club) return false;
      if (!q) return true;
      return [fullName(r), catName(r), clubName(r), r.reg_correo || '']
        .join(' ').toLowerCase().includes(q);
    });
  }, [rows, search, cat, club]);

  const total = filtered.reduce((acc, r) => acc + rowCost(r), 0);

  /** Agrupa filas por una llave y suma jugadores + costo. */
  const groupBy = (key: (r: SeguimientoRow) => string) => {
    const map = new Map<string, { count: number; amount: number }>();
    filtered.forEach(r => {
      const k = key(r);
      const cur = map.get(k) || { count: 0, amount: 0 };
      map.set(k, { count: cur.count + 1, amount: cur.amount + rowCost(r) });
    });
    return Array.from(map.entries()).sort((a, b) => b[1].count - a[1].count);
  };

  const byCat = useMemo(() => groupBy(catName), [filtered]);
  const byClub = useMemo(() => groupBy(clubName), [filtered]);

  return (
    <div className="space-y-6">
      {/* Resumen */}
      <div className="grid gap-4 sm:grid-cols-3">
        <Card>
          <CardContent className="pt-6 flex items-center gap-3">
            <Users className="w-8 h-8 text-primary" />
            <div>
              <p className="text-2xl font-bold">{filtered.length}</p>
              <p className="text-sm text-muted-foreground">Pre-registros</p>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6 flex items-center gap-3">
            <Building2 className="w-8 h-8 text-primary" />
            <div>
              <p className="text-2xl font-bold">{byClub.length}</p>
              <p className="text-sm text-muted-foreground">Clubes</p>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6 flex items-center gap-3">
            <Wallet className="w-8 h-8 text-primary" />
            <div>
              <p className="text-2xl font-bold">{money(total, currency)}</p>
              <p className="text-sm text-muted-foreground">Costo acumulado</p>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Filtros */}
      <Card>
        <CardContent className="pt-6 flex flex-col gap-3 md:flex-row md:items-center">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              value={search}
              onChange={e => setSearch(e.target.value)}
              placeholder="Buscar jugador, club o correo"
              className="pl-10"
            />
          </div>
          <Select value={cat} onValueChange={setCat}>
            <SelectTrigger className="md:w-56"><SelectValue placeholder="Categoría" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todas las categorías</SelectItem>
              {cats.map(c => <SelectItem key={c} value={c}>{c}</SelectItem>)}
            </SelectContent>
          </Select>
          <Select value={club} onValueChange={setClub}>
            <SelectTrigger className="md:w-56"><SelectValue placeholder="Club" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todos los clubes</SelectItem>
              {clubs.map(c => <SelectItem key={c} value={c}>{c}</SelectItem>)}
            </SelectContent>
          </Select>
          <Button variant="outline" onClick={refresh} disabled={loading}>
            {loading ? <Loader2 className="w-4 h-4 animate-spin" /> : <RefreshCw className="w-4 h-4" />}
            <span className="ml-2">Actualizar</span>
          </Button>
        </CardContent>
      </Card>

      {/* Tabla principal */}
      <Card>
        <CardHeader>
          <CardTitle>Pre-registros recibidos</CardTitle>
          <CardDescription>Jugador, categoría, club y costo de inscripción.</CardDescription>
        </CardHeader>
        <CardContent className="overflow-x-auto">
          {loading && rows.length === 0 ? (
            <div className="py-12 flex justify-center"><Loader2 className="w-6 h-6 animate-spin text-primary" /></div>
          ) : filtered.length === 0 ? (
            <p className="py-12 text-center text-muted-foreground">Sin pre-registros para mostrar.</p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-left text-muted-foreground">
                  <th className="py-2 pr-3">Jugador</th>
                  <th className="py-2 pr-3">Categoría</th>
                  <th className="py-2 pr-3">Club</th>
                  <th className="py-2 pr-3 text-right">Costo</th>
                  <th className="py-2 pr-3">Fecha</th>
                </tr>
              </thead>
              <tbody>
                {filtered.map(r => (
                  <tr key={r.id} className="border-b last:border-0">
                    <td className="py-2 pr-3 font-medium">{fullName(r)}</td>
                    <td className="py-2 pr-3">
                      <Badge variant="secondary">{catName(r)}</Badge>
                    </td>
                    <td className="py-2 pr-3">{clubName(r)}</td>
                    <td className="py-2 pr-3 text-right font-semibold">{money(rowCost(r), currency)}</td>
                    <td className="py-2 pr-3 whitespace-nowrap text-muted-foreground">{rowDate(r)}</td>
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr className="bg-primary/10 font-bold">
                  <td className="py-2 pr-3" colSpan={3}>Total</td>
                  <td className="py-2 pr-3 text-right">{money(total, currency)}</td>
                  <td />
                </tr>
              </tfoot>
            </table>
          )}
        </CardContent>
      </Card>

      {/* Resúmenes */}
      <div className="grid gap-6 lg:grid-cols-2">
        {[
          { title: 'Por categoría', desc: 'Jugadores y costo acumulado por categoría.', data: byCat, head: 'Categoría' },
          { title: 'Por club', desc: 'Jugadores y costo acumulado por club.', data: byClub, head: 'Club' },
        ].map(block => (
          <Card key={block.title}>
            <CardHeader>
              <CardTitle>{block.title}</CardTitle>
              <CardDescription>{block.desc}</CardDescription>
            </CardHeader>
            <CardContent className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <th className="py-2 pr-3">{block.head}</th>
                    <th className="py-2 pr-3 text-right">Jugadores</th>
                    <th className="py-2 pr-3 text-right">Costo</th>
                  </tr>
                </thead>
                <tbody>
                  {block.data.length === 0 ? (
                    <tr><td colSpan={3} className="py-6 text-center text-muted-foreground">Sin datos</td></tr>
                  ) : block.data.map(([k, v]) => (
                    <tr key={k} className="border-b last:border-0">
                      <td className="py-2 pr-3">{k}</td>
                      <td className="py-2 pr-3 text-right">{v.count}</td>
                      <td className="py-2 pr-3 text-right font-semibold">{money(v.amount, currency)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
};

// ============= Page =============

const AdminRegistrosSeguimiento = () => {
  const [password, setPassword] = useState<string | null>(null);

  useEffect(() => {
    if (localStorage.getItem(SESSION_KEY) === REGISTROS_PASSWORD) {
      setPassword(REGISTROS_PASSWORD);
    }
  }, []);

  const login = (pwd: string): boolean => {
    if (pwd === REGISTROS_PASSWORD) {
      localStorage.setItem(SESSION_KEY, REGISTROS_PASSWORD);
      setPassword(REGISTROS_PASSWORD);
      return true;
    }
    return false;
  };

  return (
    <Layout>
      <div className="container mx-auto px-4 py-8">
        {!password ? (
          <LoginForm onLogin={login} />
        ) : (
          <>
            <div className="mb-6">
              <h1 className="text-3xl font-bold">Seguimiento de Pre-Registros</h1>
              <p className="text-muted-foreground">Categoría, club y costo de cada pre-registro recibido.</p>
            </div>
            <SeguimientoDashboard password={password} />
          </>
        )}
      </div>
    </Layout>
  );
};

export default AdminRegistrosSeguimiento;
