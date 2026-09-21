/**
 * AnnouncementRibbon
 * -----------------------------------------------------------------------
 * Site-wide scrolling text ribbon rendered between the top header and the
 * sponsor ribbon. Configuration lives in `site_config.anuncio_config`
 * (managed from Admin > Anuncio) and applies to every page of the site.
 *
 * Reuses the existing `scroll-sponsors` keyframes (translateX 0 → -50%)
 * with a duplicated content string, so the loop is seamless.
 */
import { useEffect, useMemo, useRef, useState, type CSSProperties } from 'react';
import { useLocation } from 'react-router-dom';
import { useSiteConfig } from '@/hooks/useSiteConfig';
import type { AnuncioConfig } from '@/hooks/useSiteConfig';

/**
 * Maps the admin-selected font family preset to a CSS font-family stack.
 * Kept in sync with the AdminAnuncio font picker.
 */
const FONT_FAMILY_MAP: Record<string, string> = {
  sans: 'ui-sans-serif, system-ui, sans-serif',
  serif: 'ui-serif, Georgia, serif',
  mono: 'ui-monospace, SFMono-Regular, monospace',
  display: '"Playfair Display", Georgia, serif',
};

type ViewportKind = 'mobile' | 'tablet' | 'desktop';

const MEXICO_TIME_ZONE = 'America/Mexico_City';

const getViewportKind = (): ViewportKind => {
  if (typeof window === 'undefined') return 'desktop';
  if (window.innerWidth < 768) return 'mobile';
  if (window.innerWidth < 1024) return 'tablet';
  return 'desktop';
};

const getMexicoDateParts = (date: Date) => {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: MEXICO_TIME_ZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  }).formatToParts(date);
  const values = Object.fromEntries(parts.map((part) => [part.type, part.value]));
  const hour = values.hour === '24' ? '00' : values.hour;
  return `${values.year}-${values.month}-${values.day}T${hour}:${values.minute}:${values.second}`;
};

const toComparableDateTime = (date?: string, time?: string) => {
  if (!date || !time) return null;
  return `${date}T${time.length === 5 ? `${time}:00` : time}`;
};

const isInsideTimerWindow = (cfg: AnuncioConfig, nowMexico: string) => {
  if (!cfg.timerEnabled) return true;
  const startsAt = toComparableDateTime(cfg.startDate, cfg.startTime);
  const endsAt = toComparableDateTime(cfg.endDate, cfg.endTime);
  if (!startsAt || !endsAt) return false;
  return nowMexico >= startsAt && nowMexico <= endsAt;
};

const shouldStickForViewport = (cfg: AnuncioConfig, viewport: ViewportKind) => {
  if (viewport === 'mobile') return cfg.stickyMobile === true;
  if (viewport === 'tablet') return cfg.stickyTablet === true;
  return cfg.stickyDesktop === true;
};

/**
 * Renders a single scrolling ribbon for one AnuncioConfig slot.
 * Kept as a subcomponent so the parent can stack N of them vertically.
 */
const RibbonRow = ({ cfg }: { cfg: AnuncioConfig }) => {
  const raw = (cfg.text ?? '').trim();
  if (!raw) return null;
  const unit = `${raw}\u00A0\u00A0\u00A0•\u00A0\u00A0\u00A0`;
  const repeatedText = unit.repeat(8);
  const speed = Math.max(5, Number(cfg.speedSeconds) || 30);
  const textStyle: React.CSSProperties = {
    color: cfg.textColor || '#ffffff',
    fontFamily: FONT_FAMILY_MAP[cfg.fontFamily] || FONT_FAMILY_MAP.sans,
    fontSize: `${cfg.fontSize || 16}px`,
    fontWeight: cfg.bold ? 700 : 500,
    fontStyle: cfg.italic ? 'italic' : 'normal',
    letterSpacing: '0.02em',
  };
  return (
    <div
      className="w-full overflow-hidden border-y border-border"
      style={{ backgroundColor: cfg.bgColor || '#111827' }}
      role="marquee"
      aria-label="Anuncio del torneo"
    >
      <div
        className="sponsor-scroll flex whitespace-nowrap py-2"
        style={{ animationDuration: `${speed}s` }}
      >
        <span className="px-4" style={textStyle}>{repeatedText}</span>
        <span className="px-4" aria-hidden="true" style={textStyle}>{repeatedText}</span>
      </div>
    </div>
  );
};

/**
 * AnnouncementRibbon
 * Reads global config and renders up to 3 scrolling messages stacked
 * vertically, one per enabled/matching slot. Returns null when nothing
 * qualifies for the current route.
 */
const AnnouncementRibbon = () => {
  const { data: siteConfig } = useSiteConfig();
  const location = useLocation();
  const stickyRef = useRef<HTMLDivElement | null>(null);
  const [viewport, setViewport] = useState<ViewportKind>(() => getViewportKind());
  const [nowMexico, setNowMexico] = useState(() => getMexicoDateParts(new Date()));
  const raw = siteConfig?.anuncio_config;
  // Normalize legacy single-object payloads to an array.
  const slots: AnuncioConfig[] = Array.isArray(raw) ? raw : raw ? [raw] : [];

  useEffect(() => {
    const updateViewport = () => setViewport(getViewportKind());
    updateViewport();
    window.addEventListener('resize', updateViewport);
    return () => window.removeEventListener('resize', updateViewport);
  }, []);

  useEffect(() => {
    const updateNow = () => setNowMexico(getMexicoDateParts(new Date()));
    updateNow();
    const intervalId = window.setInterval(updateNow, 30_000);
    return () => window.clearInterval(intervalId);
  }, []);

  const active = useMemo(() => slots.filter((cfg) => {
    if (!cfg?.enabled) return false;
    if (!cfg.text || !cfg.text.trim()) return false;
    if (!isInsideTimerWindow(cfg, nowMexico)) return false;
    const paths = cfg.paths;
    return (
      !paths ||
      paths.length === 0 ||
      paths.includes('*') ||
      paths.includes(location.pathname)
    );
  }), [location.pathname, nowMexico, slots]);

  const stickyActive = active.filter((cfg) => shouldStickForViewport(cfg, viewport));
  const normalActive = active.filter((cfg) => !shouldStickForViewport(cfg, viewport));

  useEffect(() => {
    const el = stickyRef.current;
    if (stickyActive.length === 0 || !el) {
      document.documentElement.style.setProperty('--announcement-ribbon-height', '0px');
      return () => document.documentElement.style.setProperty('--announcement-ribbon-height', '0px');
    }

    const updateHeight = () => {
      document.documentElement.style.setProperty(
        '--announcement-ribbon-height',
        `${el.getBoundingClientRect().height}px`,
      );
    };
    updateHeight();
    const observer = new ResizeObserver(updateHeight);
    observer.observe(el);
    window.addEventListener('resize', updateHeight);
    return () => {
      observer.disconnect();
      window.removeEventListener('resize', updateHeight);
      document.documentElement.style.setProperty('--announcement-ribbon-height', '0px');
    };
  }, [stickyActive.length]);

  if (active.length === 0) return null;
  return (
    <>
      {normalActive.length > 0 && (
        <div className="w-full flex flex-col">
          {normalActive.map((cfg, i) => (
            <RibbonRow key={`normal-${i}`} cfg={cfg} />
          ))}
        </div>
      )}
      {stickyActive.length > 0 && (
        <div
          ref={stickyRef}
          className="sticky top-[var(--header-height,0px)] z-[45] w-full flex flex-col shadow-sm"
        >
          {stickyActive.map((cfg, i) => (
            <RibbonRow key={`sticky-${i}`} cfg={cfg} />
          ))}
        </div>
      )}
    </>
  );
};

export default AnnouncementRibbon;
