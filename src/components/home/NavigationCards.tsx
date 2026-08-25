/**
 * NavigationCards Component
 * Grid of navigation cards for exploring tournament sections
 * Respects page visibility settings from admin context
 */

import { Link } from 'react-router-dom';
import { usePageVisibility } from '@/contexts/PageVisibilityContext';
import type { MenuItem } from '@/data/mockData';
import { 
  FileText, Calendar, Users, Clock, Radio, Trophy, Target,
  CalendarDays, Bell, Award, Handshake, BookOpen 
} from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

// ============= Icon Mapping =============

/** Maps page IDs to their corresponding icons */
const iconMap: Record<string, React.ElementType> = {
  convocatoria: FileText,
  eventos: Calendar,
  jugadores: Users,
  'field-gira': Users,
  salidas: Clock,
  'live-scoring': Radio,
  live: Radio,
  resultados: Trophy,
  competicion: Target,
  calendario: CalendarDays,
  avisos: Bell,
  premios: Award,
  patrocinadores: Handshake,
  reglas: BookOpen,
};

// ============= Description Mapping =============

/** Maps page IDs to their descriptions */
const descriptionMap: Record<string, string> = {
  convocatoria: 'Información sobre inscripciones, fechas y requisitos',
  eventos: 'Calendario de actividades y eventos del torneo',
  jugadores: 'Lista completa de participantes inscritos',
  'field-gira': 'Field de la gira por categoría',
  salidas: 'Horarios de salida y grupos de juego',
  'live-scoring': 'Resultados en tiempo real durante el torneo',
  live: 'Resultados en vivo y scoring en tiempo real',
  resultados: 'Consulta los resultados de cada ronda',
  competicion: 'Resultados de approach, drive y competencias especiales',
  calendario: 'Fechas importantes del torneo',
  avisos: 'Comunicados y noticias importantes',
  premios: 'Reconocimientos y premiación',
  patrocinadores: 'Empresas que apoyan el torneo',
  reglas: 'Reglamento del torneo y código de conducta',
};

// ============= Component =============

const NavigationCards = () => {
  const { visibilitySettings } = usePageVisibility();
  /** Filter cards to only show pages that are visible (respects admin settings even for admins) */
  const menuItems = usePageVisibility().getVisibleMenuItems()
    .filter(item => item.id !== 'home')
    .filter(item => visibilitySettings[item.id] !== false);

  return (
    <section className="py-16 md:py-24 bg-background">
      <div className="container mx-auto px-4">
        {/* Section Header */}
        <div className="text-center mb-12">
          <h2 className="text-3xl md:text-4xl font-display font-bold text-foreground mb-4">
            Explora el Torneo
          </h2>
          <p className="text-lg text-muted-foreground max-w-2xl mx-auto">
            Todo lo que necesitas saber sobre el torneo de golf amateur más importante del país
          </p>
        </div>

        {/* Navigation Cards Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          {menuItems.map((item: MenuItem, index: number) => {
            const Icon = iconMap[item.id] || FileText;
            return (
              <Link key={item.id} to={item.path}>
                <Card 
                  className={cn(
                    "card-hover border-border/50 bg-card h-full",
                    "animate-fade-in-up opacity-0"
                  )}
                  style={{ animationDelay: `${index * 50}ms`, animationFillMode: 'forwards' }}
                >
                  <CardContent className="p-6">
                    <div className="flex items-start gap-4">
                      <div className="w-12 h-12 rounded-xl gradient-hero flex items-center justify-center flex-shrink-0">
                        <Icon className="h-6 w-6 text-primary-foreground" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <h3 className="font-semibold text-foreground mb-1">
                          {item.label}
                        </h3>
                        <p className="text-sm text-muted-foreground leading-relaxed">
                          {descriptionMap[item.id] || 'Información del torneo'}
                        </p>
                        <span className="inline-flex items-center text-sm font-medium text-primary mt-3 group-hover:text-accent transition-colors">
                          Ver más →
                        </span>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </Link>
            );
          })}
        </div>
      </div>
    </section>
  );
};

export default NavigationCards;
