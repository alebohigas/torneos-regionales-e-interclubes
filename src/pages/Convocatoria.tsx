/**
 * Bases Page
 * Displays tournament bases with 8 dynamic, reorderable sections
 * Sections: Descripción, Elegibilidad, Costos, Categorías, Premiación,
 *           Calendario, Reglas Locales, Competencias Especiales
 */

import { useState, useEffect } from 'react';
import Layout from '@/components/layout/Layout';
import PageHero from '@/components/shared/PageHero';
import convocatoriaHero from '@/assets/convocatoria-hero.jpg';
import PageSubmenu from '@/components/convocatoria/PageSubmenu';
import { useTournamentInfo } from '@/hooks/useTournamentData';
import { useConvocatoriaSections } from '@/hooks/useConvocatoriaSections';
import { useConvocatoriaContent, type ConvocatoriaContentRow } from '@/hooks/useConvocatoriaContent';
import { useUploadsList } from '@/hooks/useUploads';
import { useGiraId } from '@/hooks/useGiraId';
import { useTorneoId } from '@/hooks/useTorneoId';
import { getBasesDocumentUrl } from '@/config/basesDocuments';
import { Calendar } from 'lucide-react';
import { FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';

// Section components
import DescripcionSection from '@/components/convocatoria/DescripcionSection';
import ElegibilidadSection from '@/components/convocatoria/ElegibilidadSection';
import CostosSection from '@/components/convocatoria/CostosSection';
import CategoriasSection from '@/components/convocatoria/CategoriasSection';
import PremiacionSection from '@/components/convocatoria/PremiacionSection';
import DesempatesSection from '@/components/convocatoria/DesempatesSection';
import StablefordSection from '@/components/convocatoria/StablefordSection';
import { useValorStable } from '@/hooks/useValorStable';
import ReglasSection from '@/components/convocatoria/ReglasSection';
import CompetenciasEspecialesSection from '@/components/convocatoria/CompetenciasEspecialesSection';
import ServiciosSection from '@/components/convocatoria/ServiciosSection';
import PatrocinadoresOficialesSection from '@/components/convocatoria/PatrocinadoresOficialesSection';
import CalendarioJuegoSection from '@/components/convocatoria/CalendarioJuegoSection';

// Shared types (no runtime mock fallbacks — Convocatoria is strictly DB-backed
// per torneoid via the `convocatoria_content` MySQL table).
import type {
  PremioCategoria,
  ReglaItem,
  CompetenciaEspecial,
  ServicioDia,
  PatrocinadorOficial,
  DesempatesData,
  PricingTable,
  ForaneosPricing,
  ContactInfo,
} from '@/data/mockData';
import { useCalendarioData } from '@/hooks/useCalendarioData';
import { useHorariosData } from '@/hooks/useHorariosData';
import { usePageVisibility } from '@/contexts/PageVisibilityContext';

// ============= Helpers =============

/** Regex to match leading Roman numerals */
const ROMAN_NUMERAL_REGEX = /^([IVXLCDM]+)\s+(.+)$/;

/** Split tournament name into Roman numeral prefix and rest */
const parseTournamentName = (name: string) => {
  const match = name.trim().match(ROMAN_NUMERAL_REGEX);
  return match ? { roman: match[1], rest: match[2] } : { roman: '', rest: name };
};

/**
 * Format date range in Spanish using timezone-safe parsing.
 * Decomposes 'YYYY-MM-DD' into local Date components to avoid the UTC offset
 * shift that previously caused dates to render one day earlier than the
 * Hero on the home page. This mirrors Hero.tsx so both views share the same
 * source of truth for tournament dates.
 */
const formatDate = (start: string, end: string) => {
  const [sy, sm, sd] = start.split('-').map(Number);
  const [ey, em, ed] = end.split('-').map(Number);
  const startDate = new Date(sy, sm - 1, sd);
  const endDate = new Date(ey, em - 1, ed);
  const options: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'long' };
  return `Del ${startDate.toLocaleDateString('es-MX', options)} al ${endDate.toLocaleDateString('es-MX', { ...options, year: 'numeric' })}`;
};

// ============= Section Renderer =============

/**
 * Map section ID to its component.
 *
 * STRICT DB-ONLY POLICY:
 * Every items/text-based section receives ONLY the DB payload (or empty
 * value). We intentionally do NOT fall back to mockData — leaking another
 * tournament's seeded content across torneos has caused real bugs
 * (e.g. torneoid=346 showing 354's "Patrocinadores Oficiales").
 * Each Section component already returns null when its data is empty,
 * so the page naturally hides sections with nothing to show.
 */
const renderSection = (sectionId: string, dbRow?: ConvocatoriaContentRow) => {
  /** Shorthand: DB content payload (already parsed JSON). */
  const c = dbRow?.content as any;

  switch (sectionId) {
    case 'descripcion':
      return <DescripcionSection descripcion={(c?.text as string) ?? ''} />;
    case 'elegibilidad':
      return (
        <ElegibilidadSection
          eligibilityText={(c?.eligibilityText as string) ?? ''}
          notesText={(c?.notesText as string[]) ?? []}
          inscripcionesText={(c?.inscripcionesText as string) ?? ''}
        />
      );
    case 'costos':
      return (
        <CostosSection
          sociosPricing={(c?.sociosPricing as PricingTable[]) ?? []}
          foraneosPricing={(c?.foraneosPricing as ForaneosPricing[]) ?? []}
          pricingNote={(c?.pricingNote as string) ?? ''}
          contactInfo={
            (c?.contactInfo as ContactInfo) ?? {
              bankName: '', clabe: '', cuenta: '', nombre: '',
              email: '', telefono: '', telefonoDirecto: '',
            }
          }
          contactWarning={(c?.contactWarning as string) ?? ''}
          inscripcionesText={(c?.inscripcionesText as string) ?? ''}
        />
      );
    case 'categorias':
      return <CategoriasSection />;
    case 'premiacion':
      return <PremiacionSection data={(c?.items as PremioCategoria[]) ?? []} />;
    case 'desempates':
      return (
        <DesempatesSection
          data={(c as DesempatesData) ?? { intro: '', paraCorte: [], paraTrofeos: [] }}
        />
      );
    // Valores Stableford: los datos vienen de torneos.valorstable (BD),
    // no de convocatoria_content, por eso no recibe payload.
    case 'stableford':
      return <StablefordSection />;
    case 'reglas':
      return <ReglasSection data={(c?.items as ReglaItem[]) ?? []} />;
    case 'competencias':
      return <CompetenciasEspecialesSection data={(c?.items as CompetenciaEspecial[]) ?? []} />;
    case 'servicios':
      return <ServiciosSection data={(c?.items as ServicioDia[]) ?? []} />;
    case 'calendarioJuego':
      return <CalendarioJuegoSection />;
    case 'patrocinadoresOficiales':
      return (
        <PatrocinadoresOficialesSection
          data={(c?.items as PatrocinadorOficial[]) ?? []}
        />
      );
    default:
      return null;
  }
};

// ============= Main Component =============

const Convocatoria = () => {
  const [activeSection, setActiveSection] = useState('descripcion');
  const { giraId } = useGiraId();
  const { torneoId } = useTorneoId();

  useEffect(() => {
    document.title = 'Bases';
  }, []);
  const { data: tournamentData } = useTournamentInfo();
  const { sections } = useConvocatoriaSections();
  // DB-backed convocatoria content for the active tournament.
  // When a row exists for a given section_id we use it; otherwise the
  // page falls back to the static mockData values below.
  // Auto-recarga desde la BD: poll cada 30s + refetch al enfocar la pestaña +
  // sincronización inmediata cuando /admin habilita/deshabilita una sección.
  const { bySectionId: dbContent } = useConvocatoriaContent({
    pollMs: 30_000,
    refreshOnFocus: true,
  });
  // Resolve the convocatoria PDF URL.
  // ONLY the admin-uploaded PDF (via /admin → "convocatoria" section) is used.
  // The legacy `pdfs` bucket fallback and the build-time `/convocatoria-torneo.pdf`
  // shipped under `public/` were removed because Firefox/browser refresh on the
  // Convocatoria route was occasionally re-opening the stale public PDF.
  // If no admin upload exists, the "Ver en PDF" button is hidden entirely.
  const { data: convocatoriaUploads } = useUploadsList('convocatoria');
  const firstSectionPdf = convocatoriaUploads?.files.find((f) => /\.pdf$/i.test(f.name));
  const configuredPdfUrl = getBasesDocumentUrl(giraId, torneoId);
  const convocatoriaPdfUrl = configuredPdfUrl ?? firstSectionPdf?.url ?? null;
  const parsed = tournamentData?.name ? parseTournamentName(tournamentData.name) : null;

  // ----- Auto-hide sections that have no content -----
  // Each section maps to a data source; we drop the section (and its
  // submenu entry) when the corresponding source is empty so the page
  // never shows blank/placeholder blocks for the current torneo.
  const { data: calData } = useCalendarioData();
  const { data: horData } = useHorariosData();
  const { isPageVisible } = usePageVisibility();
  // Valores Stableford del torneo activo (torneos.valorstable). Se consulta
  // aquí para que la sección y su entrada en el submenú solo aparezcan
  // cuando la BD realmente tiene puntajes para este torneoid.
  const { rows: stablefordRows } = useValorStable();

  const hasCalendarioJuego =
    (isPageVisible('calendario') && (calData?.entries?.length ?? 0) > 0) ||
    (isPageVisible('horarios')   && (horData?.entries?.length ?? 0) > 0);

  /**
   * Returns true when the section has data worth rendering.
   * Prefers a DB row when present (and respects its `enabled` flag);
   * otherwise falls back to the legacy mockData presence check.
   */
  const sectionHasContent = (id: string): boolean => {
    // API-driven sections: presence is decided by their own data sources,
    // not by `convocatoria_content`.
    if (id === 'categorias') return true;
    if (id === 'calendarioJuego') return hasCalendarioJuego;
    // Valores Stableford: la presencia la decide `torneos.valorstable`
    // (fila del torneoid activo). Sin puntajes -> sección y submenú ocultos.
    if (id === 'stableford') return stablefordRows.length > 0;

    // DB-only sections: render only when there is an enabled DB row
    // with non-empty content for the active torneoid. No mock fallback.
    const dbRow = dbContent.get(id);
    if (!dbRow || !dbRow.enabled) return false;
    const c = dbRow.content as any;
    if (c === null || c === undefined) return false;
    if (Array.isArray(c)) return c.length > 0;
    if (typeof c === 'object') {
      // Items-based payload ({ items: [...] }) -> require at least one item.
      if (Array.isArray(c.items)) return c.items.length > 0;
      // Otherwise: render if any field is non-empty.
      return Object.values(c).some((v) =>
        typeof v === 'string'
          ? v.trim() !== ''
          : Array.isArray(v)
            ? v.length > 0
            : v != null
      );
    }
    if (typeof c === 'string') return c.trim() !== '';
    return true;
  };

  /**
   * Visibilidad efectiva de una sección.
   *
   * La BD (`convocatoria_content.enabled`) es la AUTORIDAD cuando existe
   * fila para el torneo, porque es compartida por todos los dispositivos.
   * El flag local (localStorage / defaults) solo aplica cuando no hay fila,
   * lo que antes provocaba que una sección activada en /admin (p. ej.
   * "Desempate") no se viera en otros dispositivos/navegadores.
   */
  const isSectionEnabled = (id: string, localEnabled: boolean): boolean => {
    const dbRow = dbContent.get(id);
    if (dbRow) return dbRow.enabled;
    return localEnabled;
  };

  /** Enabled sections, additionally filtered by content presence. */
  const enabledSections = sections.filter(
    (s) => isSectionEnabled(s.id, s.enabled) && sectionHasContent(s.id)
  );

  /** Scroll-based active section tracking */
  useEffect(() => {
    const handleScroll = () => {
      const offset = 150;
      for (const section of [...enabledSections].reverse()) {
        const element = document.getElementById(section.id);
        if (element) {
          const rect = element.getBoundingClientRect();
          if (rect.top <= offset) {
            setActiveSection(section.id);
            break;
          }
        }
      }
    };

    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, [enabledSections]);

  return (
    <Layout>
      <PageHero
        title="Bases"
        subtitle="Información completa sobre inscripciones, categorías y requisitos"
        backgroundImage={convocatoriaHero}
        // Midpoint between the previous 65% framing and the lower 95%
        // framing — keeps the desk and bell visible without cropping them.
        backgroundPosition="center 80%"
      />

      {/* Sticky submenu */}
      <PageSubmenu
        sections={enabledSections.map((s) => ({ id: s.id, label: s.label }))}
        activeSection={activeSection}
      />

      <section className="py-16 bg-background">
        <div className="container mx-auto px-4">
          {/* PDF download button — opens the admin-uploaded convocatoria PDF in
              a new tab. Hidden when no PDF has been uploaded via /admin. */}
          {convocatoriaPdfUrl && (
            <div className="flex justify-center mb-8">
              <Button
                asChild
                size="lg"
                className="gap-2 bg-blue-600 hover:bg-blue-700 text-white"
              >
                <a
                  href={convocatoriaPdfUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label="Ver bases en PDF"
                >
                  <FileText className="h-5 w-5" />
                  Ver en PDF
                </a>
              </Button>
            </div>
          )}

          {/* Tournament header */}
          <div className="text-center mb-16">
            <div className="inline-flex items-center gap-4 mb-6">
              {tournamentData?.logoUrl ? (
                <img
                  src={tournamentData.logoUrl}
                  alt={tournamentData.name}
                  className="w-20 h-20 rounded-2xl object-contain shadow-elevated"
                />
              ) : (
                <div className="w-20 h-20 rounded-2xl gradient-hero flex items-center justify-center text-primary-foreground font-display font-bold text-3xl shadow-elevated">
                  {parsed?.roman || ''}
                </div>
              )}
              <div className="text-left">
                <h2 className="text-2xl md:text-3xl font-display font-bold text-foreground">
                  {parsed?.rest || tournamentData?.name || 'Torneo de Golf'}
                </h2>
                <p className="text-lg font-display italic text-muted-foreground">
                  {tournamentData?.club || ''}
                </p>
              </div>
            </div>
            {tournamentData?.startDate && tournamentData?.endDate && (
              <div className="flex items-center justify-center gap-2 text-lg text-accent">
                <Calendar className="h-5 w-5" />
                <span className="font-medium">
                  {formatDate(tournamentData.startDate, tournamentData.endDate)}
                </span>
              </div>
            )}
          </div>

          {/* Dynamic sections rendered in order */}
          {enabledSections.map((section) => (
            <div key={section.id} id={section.id} className="mb-16 scroll-mt-32">
              {renderSection(section.id, dbContent.get(section.id))}
            </div>
          ))}
        </div>
      </section>
    </Layout>
  );
};

export default Convocatoria;
