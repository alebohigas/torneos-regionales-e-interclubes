import { useNavigate } from 'react-router-dom';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { FileSpreadsheet, Printer, ArrowRight } from 'lucide-react';

const AdminAleinSystem = () => {
  const navigate = useNavigate();

  return (
    <div className="space-y-5">
      <div>
        <h2 className="text-xl font-bold">ALEIN SYSTEM</h2>
        <p className="text-sm text-muted-foreground">Formatos operativos listos para impresión.</p>
      </div>
      <div className="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-md bg-primary/10">
              <Printer className="h-5 w-5 text-primary" />
            </div>
            <CardTitle>Tarjetas para impresión</CardTitle>
            <CardDescription>Genera tarjetas por fecha, campo y categoría, tres por hoja carta.</CardDescription>
          </CardHeader>
          <CardContent>
            <Button className="w-full gap-2" onClick={() => navigate('/admin/alein-system/tarjetas')}>
              Abrir tarjetas <ArrowRight className="h-4 w-4" />
            </Button>
          </CardContent>
        </Card>

        <Card className="opacity-70">
          <CardHeader>
            <div className="mb-2 flex items-center justify-between">
              <div className="flex h-10 w-10 items-center justify-center rounded-md bg-muted">
                <FileSpreadsheet className="h-5 w-5 text-muted-foreground" />
              </div>
              <Badge variant="secondary">Próximamente</Badge>
            </div>
            <CardTitle>Salidas para impresión</CardTitle>
            <CardDescription>Formato impreso de grupos, horarios y hoyos de salida.</CardDescription>
          </CardHeader>
          <CardContent>
            <Button variant="outline" className="w-full" disabled>Próxima etapa</Button>
          </CardContent>
        </Card>
      </div>
    </div>
  );
};

export default AdminAleinSystem;