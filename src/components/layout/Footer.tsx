/**
 * Footer Component
 * Active gira information in the footer.
 */

import { useGiraInfo } from '@/hooks/useGiraData';
import { useSiteConfig } from '@/hooks/useSiteConfig';

const Footer = () => {
  const { data: gira } = useGiraInfo();
  const { data: siteConfig } = useSiteConfig();

  /**
   * Prioridad del tagline:
   *   1. Override manual desde /admin → Estadísticas Página → Slogan del footer.
   *   2. Default global para la gira.
   */
  const adminTagline = siteConfig?.stats_page_config?.overrides?.footerTagline?.trim();
  const tagline =
    adminTagline ||
    'El golf que conecta cada copa y cada torneo.';

  return (
    <footer className="bg-primary text-primary-foreground">
      <div className="container mx-auto py-12 px-4">
        <div className="grid grid-cols-1 gap-8">
          {/* Gira Info */}
          <div>
            <div className="flex items-center gap-3 mb-4">
              <div className="w-12 h-12 rounded-lg bg-primary-foreground/10 flex items-center justify-center font-display font-bold text-xl text-secondary">GT</div>
              <div>
                <h3 className="font-display font-semibold">
                  {gira?.name || 'Golf Tour'}
                </h3>
                {gira?.uso === 0 && <p className="text-sm text-primary-foreground/70">Copa terminada</p>}
              </div>
            </div>
            <p className="text-sm text-primary-foreground/80 leading-relaxed">
              {tagline}
            </p>
          </div>

        </div>

        {/* Copyright */}
        <div className="mt-10 pt-6 border-t border-primary-foreground/20 text-center">
          <p className="text-sm text-primary-foreground/60">
            © {new Date().getFullYear()} {gira?.name || 'Golf Tour'}. Todos los derechos reservados.
          </p>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
