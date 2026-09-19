/**
 * AdminStaffUsers
 * -----------------------------------------------------------------
 * CRUD de usuarios staff temporales (tipo=99 en `usuarios`).
 * Cada uno tiene rango de fechas (`desde`/`hasta`), password,
 * y un set de checkboxes que define a qué áreas de /admin pueden
 * acceder (preregistros, brackets, banderas, pop, etc.).
 *
 * Sólo se llama desde el panel admin principal con la password
 * legacy `admin2025` (heredada de PageVisibilityContext).
 */
import { useEffect, useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/hooks/use-toast';
import { API_BASE_URL } from '@/config/api';
import { useTorneoId } from '@/hooks/useTorneoId';
import type { StaffArea } from '@/contexts/StaffAuthContext';
import { Loader2, Trash2, Plus, UserCog, KeyRound } from 'lucide-react';

const ADMIN_PWD = 'admin2025';

/** Áreas del admin que se pueden asignar al staff. Debe coincidir con server. */
export const STAFF_AREAS: { id: StaffArea; label: string }[] = [
  { id: 'preregistros', label: 'Pre-Registros' },
  { id: 'brackets',     label: 'Brackets Putt' },
  { id: 'matchplay',    label: 'Match Play' },
  { id: 'live',         label: 'Live' },
  { id: 'banderas',     label: 'Banderas' },
  { id: 'pop',          label: 'POP-UP' },
  { id: 'eventos',      label: 'Eventos' },
  { id: 'avisos',       label: 'Avisos' },
  { id: 'premios',      label: 'Premios' },
  { id: 'hoteles',      label: 'Hoteles' },
  { id: 'convocatoria', label: 'Convocatoria' },
  { id: 'reglas',       label: 'Reglas' },
  { id: 'uploads',      label: 'Archivos' },
  { id: 'stats',        label: 'Estadísticas' },
];

interface StaffUser {
  id: number;
  usuario: string;
  nombre: string;
  torneoid: number;
  desde: string;
  hasta: string;
  activo: number;
  estatus: string;
  areas: string[];
}

const emptyForm = {
  usuario: '',
  nombre: '',
  password_user: '',
  desde: '',
  hasta: '',
  areas: [] as StaffArea[],
};

export default function AdminStaffUsers() {
  const { toast } = useToast();
  const { torneoId } = useTorneoId();
  const [users, setUsers] = useState<StaffUser[]>([]);
  const [loading, setLoading] = useState(false);
  const [form, setForm] = useState({ ...emptyForm });
  const [resetPwdFor, setResetPwdFor] = useState<number | null>(null);
  const [newPwd, setNewPwd] = useState('');

  const load = async () => {
    setLoading(true);
    try {
      const url = `${API_BASE_URL}/staff_users.php?password=${ADMIN_PWD}${torneoId ? `&torneoid=${torneoId}` : ''}`;
      const r = await fetch(url);
      const d = await r.json();
      if (!r.ok) throw new Error(d.error || 'Error');
      setUsers(d.users || []);
    } catch (e: any) {
      toast({ title: 'Error', description: e.message, variant: 'destructive' });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); /* eslint-disable-next-line */ }, [torneoId]);

  const toggleFormArea = (a: StaffArea) => {
    setForm(f => ({
      ...f,
      areas: f.areas.includes(a) ? f.areas.filter(x => x !== a) : [...f.areas, a],
    }));
  };

  const create = async () => {
    if (!form.usuario || !form.password_user || !form.desde || !form.hasta) {
      toast({ title: 'Faltan campos', description: 'Usuario, password y fechas son obligatorios.', variant: 'destructive' });
      return;
    }
    try {
      const r = await fetch(`${API_BASE_URL}/staff_users.php?action=create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          password: ADMIN_PWD,
          ...form,
          torneoid: torneoId ? parseInt(torneoId) : 0,
        }),
      });
      const d = await r.json();
      if (!r.ok) throw new Error(d.error || 'Error');
      toast({ title: 'Usuario creado', description: form.usuario });
      setForm({ ...emptyForm });
      load();
    } catch (e: any) {
      toast({ title: 'Error', description: e.message, variant: 'destructive' });
    }
  };

  const update = async (u: StaffUser, patch: Partial<StaffUser> & { password_user?: string; areas?: string[] }) => {
    try {
      const r = await fetch(`${API_BASE_URL}/staff_users.php?action=update`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ password: ADMIN_PWD, id: u.id, ...patch }),
      });
      const d = await r.json();
      if (!r.ok) throw new Error(d.error || 'Error');
      load();
    } catch (e: any) {
      toast({ title: 'Error', description: e.message, variant: 'destructive' });
    }
  };

  const del = async (u: StaffUser) => {
    if (!confirm(`¿Eliminar usuario "${u.usuario}"?`)) return;
    try {
      const r = await fetch(`${API_BASE_URL}/staff_users.php?action=delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ password: ADMIN_PWD, id: u.id }),
      });
      const d = await r.json();
      if (!r.ok) throw new Error(d.error || 'Error');
      toast({ title: 'Usuario eliminado' });
      load();
    } catch (e: any) {
      toast({ title: 'Error', description: e.message, variant: 'destructive' });
    }
  };

  const toggleArea = (u: StaffUser, area: StaffArea) => {
    const areas = u.areas.includes(area) ? u.areas.filter(a => a !== area) : [...u.areas, area];
    update(u, { areas });
  };

  const today = new Date().toISOString().slice(0, 10);
  const isExpired = (u: StaffUser) => u.hasta && u.hasta < today;

  return (
    <div className="space-y-6">
      {/* Crear usuario */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Plus className="h-5 w-5 text-primary" /> Crear usuario staff
          </CardTitle>
          <CardDescription>
            Acceso temporal a secciones específicas de /admin. Expira automáticamente al pasar la fecha "hasta".
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div className="space-y-1">
              <Label>Usuario</Label>
              <Input value={form.usuario} onChange={e => setForm({ ...form, usuario: e.target.value })} placeholder="staff_torneo346" />
            </div>
            <div className="space-y-1">
              <Label>Nombre</Label>
              <Input value={form.nombre} onChange={e => setForm({ ...form, nombre: e.target.value })} placeholder="Juan Pérez" />
            </div>
            <div className="space-y-1">
              <Label>Password</Label>
              <Input value={form.password_user} onChange={e => setForm({ ...form, password_user: e.target.value })} placeholder="••••••" />
            </div>
            <div className="space-y-1">
              <Label>Torneo ID</Label>
              <Input value={torneoId || ''} disabled className="font-mono" />
            </div>
            <div className="space-y-1">
              <Label>Desde</Label>
              <Input type="date" value={form.desde} onChange={e => setForm({ ...form, desde: e.target.value })} />
            </div>
            <div className="space-y-1">
              <Label>Hasta</Label>
              <Input type="date" value={form.hasta} onChange={e => setForm({ ...form, hasta: e.target.value })} />
            </div>
          </div>
          <div>
            <Label className="mb-2 block">Áreas permitidas</Label>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
              {STAFF_AREAS.map(a => (
                <label key={a.id} className="flex items-center gap-2 p-2 border rounded cursor-pointer hover:bg-muted/50">
                  <Checkbox checked={form.areas.includes(a.id)} onCheckedChange={() => toggleFormArea(a.id)} />
                  <span className="text-sm">{a.label}</span>
                </label>
              ))}
            </div>
          </div>
          <Button onClick={create}>Crear usuario</Button>
        </CardContent>
      </Card>

      {/* Lista de usuarios */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <UserCog className="h-5 w-5 text-primary" />
            Usuarios staff ({users.length})
          </CardTitle>
          <CardDescription>
            Los usuarios pierden acceso automáticamente cuando la fecha "hasta" pasa o se desactivan.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {loading ? (
            <div className="flex items-center gap-2 text-muted-foreground"><Loader2 className="h-4 w-4 animate-spin" /> Cargando...</div>
          ) : users.length === 0 ? (
            <p className="text-muted-foreground text-sm">No hay usuarios staff creados.</p>
          ) : users.map(u => (
            <div key={u.id} className="border rounded-lg p-4 space-y-3">
              <div className="flex items-start justify-between gap-2 flex-wrap">
                <div>
                  <div className="font-semibold flex items-center gap-2">
                    {u.usuario}
                    {isExpired(u) && <Badge variant="destructive">Expirado</Badge>}
                    {!u.activo && <Badge variant="secondary">Inactivo</Badge>}
                  </div>
                  <div className="text-sm text-muted-foreground">{u.nombre}</div>
                  <div className="text-xs text-muted-foreground mt-1">
                    {u.desde} → {u.hasta} · torneo {u.torneoid}
                  </div>
                </div>
                <div className="flex gap-2 items-center">
                  <label className="flex items-center gap-1 text-sm">
                    <Checkbox checked={!!u.activo} onCheckedChange={(v) => update(u, { activo: v ? 1 : 0 })} />
                    Activo
                  </label>
                  <Button size="sm" variant="outline" onClick={() => { setResetPwdFor(u.id); setNewPwd(''); }}>
                    <KeyRound className="h-3.5 w-3.5 mr-1" /> Reset PWD
                  </Button>
                  <Button size="sm" variant="destructive" onClick={() => del(u)}>
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                </div>
              </div>

              {resetPwdFor === u.id && (
                <div className="flex gap-2 items-end">
                  <div className="flex-1">
                    <Label>Nueva password</Label>
                    <Input value={newPwd} onChange={e => setNewPwd(e.target.value)} placeholder="••••••" />
                  </div>
                  <Button onClick={() => { if (newPwd) { update(u, { password_user: newPwd } as any); setResetPwdFor(null); } }}>Guardar</Button>
                  <Button variant="ghost" onClick={() => setResetPwdFor(null)}>Cancelar</Button>
                </div>
              )}

              <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
                {STAFF_AREAS.map(a => (
                  <label key={a.id} className="flex items-center gap-2 p-1.5 border rounded cursor-pointer hover:bg-muted/50">
                    <Checkbox
                      checked={u.areas.includes(a.id)}
                      onCheckedChange={() => toggleArea(u, a.id)}
                    />
                    <span className="text-xs">{a.label}</span>
                  </label>
                ))}
              </div>

              <div className="grid grid-cols-2 gap-2">
                <div>
                  <Label className="text-xs">Desde</Label>
                  <Input type="date" value={u.desde || ''} onChange={e => update(u, { desde: e.target.value })} />
                </div>
                <div>
                  <Label className="text-xs">Hasta</Label>
                  <Input type="date" value={u.hasta || ''} onChange={e => update(u, { hasta: e.target.value })} />
                </div>
              </div>
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  );
}