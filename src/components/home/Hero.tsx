/**
 * Hero Section
 * Displays the active gira name and status.
 * 
 * Gira names may follow the pattern: "XLVI TORNEO ANUAL SEMANA SANTA"
 * - Roman numeral prefix is displayed large in gold (secondary)
 * - Remaining name is displayed as h1 below
 * - Document title is also set dynamically
 */

import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Link } from 'react-router-dom';
import { useSiteConfig } from '@/hooks/useSiteConfig';
import { menuConfig } from '@/data/mockData';
import { useGiraInfo } from '@/hooks/useGiraData';
import heroImage from '@/assets/competicion-hero.jpg';

/** Regex to match leading Roman numerals (I, V, X, L, C, D, M) */
const ROMAN_NUMERAL_REGEX = /^([IVXLCDM]+)\s+(.+)$/;

/**
 * Split tournament name into Roman numeral prefix and the rest.
 * E.g. "XLVI TORNEO ANUAL SEMANA SANTA" → { roman: "XLVI", rest: "TORNEO ANUAL SEMANA SANTA" }
 */
const parseTournamentName = (name: string) => {
  const match = name.trim().match(ROMAN_NUMERAL_REGEX);
  if (match) {
    return { roman: match[1], rest: match[2] };
  }
  return { roman: '', rest: name };
};

const Hero = () => {
  const { data: siteConfig } = useSiteConfig();
  /**
   * Gira activa (eje del sitio). Su nombre es el título del hero; cuando
   * `uso = 0` la gira ya terminó y se muestra el aviso "COPA TERMINADA".
   */
  const { data: gira } = useGiraInfo();

  /**
   * Resuelve un slot del hero:
   *   1. Toma el pageId configurado desde /admin → Página → Botones Home.
   *   2. Verifica que la página exista y no esté oculta en visibility.
   *   3. Si falla cualquiera de los dos, cae al fallback del slot.
   *
   * Fallbacks:
   *   - Slot 1 → /convocatoria
   *   - Slot 2 → /jugadores
   */
  const fallback1 = { label: 'Ver Convocatoria', href: '/convocatoria' };
  const fallback2 = { label: 'Ver Jugadores', href: '/jugadores' };

  const resolveSlot = (pageId: string | null | undefined, fallback: { label: string; href: string }) => {
    if (!pageId) return fallback;
    const item = menuConfig.find((m) => m.id === pageId);
    if (!item) return fallback;
    const visible = siteConfig?.visibility?.[pageId];
    // If visibility isn't defined, we assume the page is visible (legacy).
    if (visible === false) return fallback;
    return {
      label: item.label.charAt(0) + item.label.slice(1).toLowerCase(),
      href:  item.path,
    };
  };

  const [cfg1, cfg2] = siteConfig?.home_config?.buttons ?? [null, null];
  const slot1 = resolveSlot(cfg1, fallback1);
  const slot2 = resolveSlot(cfg2, fallback2);

  /**
   * Título del hero: nombre de la GIRA configurada.
   */
  const heroName = gira?.name || '';
  /** Gira terminada => aviso público "COPA TERMINADA". */
  const giraFinished = !!gira && gira.uso === 0;

  /** Parse name into roman numeral and rest */
  const parsed = heroName
    ? parseTournamentName(heroName)
    : { roman: '', rest: '' };

  /** Set document/tab title dynamically from the active gira. */
  useEffect(() => {
    if (heroName) {
      document.title = heroName;
    }
  }, [heroName]);

  return (
    <section className="relative min-h-[70vh] flex items-center justify-center overflow-hidden">
      {/* Background Image */}
      <div 
        className="absolute inset-0 bg-cover bg-center bg-no-repeat"
        style={{
          backgroundImage: `url('${heroImage}')`,
        }}
      >
        <div className="absolute inset-0 bg-gradient-to-b from-golf-dark/70 via-golf-dark/50 to-golf-dark/80" />
      </div>

      {/* Content */}
      <div className="relative z-10 container mx-auto px-4 text-center">
        <div className="max-w-4xl mx-auto">
          {/* Roman numeral from tournament name (e.g. "XLVI") */}
          {parsed.roman && (
            <div className="mb-6 animate-fade-in-up">
              <span className="inline-block text-8xl md:text-9xl font-display font-bold text-secondary drop-shadow-lg">
                {parsed.roman}
              </span>
            </div>
          )}

          {/* Nombre de la gira */}
          <h1 className="text-4xl md:text-6xl lg:text-7xl font-display font-bold text-primary-foreground mb-4 animate-fade-in-up animation-delay-100">
            {parsed.rest || 'Golf Tour'}
          </h1>

          {/* Gira con uso = 0: la competencia ya concluyó */}
          {giraFinished && (
            <div className="mb-6 animate-fade-in-up animation-delay-100">
              <span className="inline-block rounded-md bg-destructive px-4 py-2 text-sm md:text-base font-semibold uppercase tracking-wide text-destructive-foreground">
                Copa terminada
              </span>
            </div>
          )}


          <div className="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4 animate-fade-in-up animation-delay-400">
            <Button asChild size="lg" className="bg-secondary text-secondary-foreground hover:bg-secondary/90 shadow-gold font-semibold px-8">
              <Link to={slot1.href}>{slot1.label}</Link>
            </Button>
            <Button asChild variant="outline" size="lg" className="border-primary-foreground/30 text-foreground bg-background/90 hover:bg-background">
              <Link to={slot2.href}>{slot2.label}</Link>
            </Button>
          </div>
        </div>
      </div>

      {/* Bottom gradient fade */}
      <div className="absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-background to-transparent" />
    </section>
  );
};

export default Hero;
