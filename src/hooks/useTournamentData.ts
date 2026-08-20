/**
 * Tournament Data Hooks
 * React Query hooks for tournament, menu, sponsors, and stats
 * These use POLL_STATIC (no polling) since data rarely changes
 */

import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/apiClient';
import { useSiteConfig } from '@/hooks/useSiteConfig';
import { getTorneoId } from '@/hooks/useTorneoId';
import {
  getMenuUrl,
  getSponsorsUrl,
  getTournamentUrl,
  getTournamentStatsUrl,
  getEventosUrl,
  POLL_STATIC,
} from '@/config/api';
import type { MenuItem, Sponsor, TournamentInfo, TournamentStats, EventDay } from '@/data/mockData';

// ============= Menu =============

/** Fetch enabled menu items from API */
export const useMenuItems = () => {
  return useQuery<MenuItem[]>({
    queryKey: ['menu'],
    queryFn: () => apiFetch<MenuItem[]>(getMenuUrl()),
    staleTime: 5 * 60 * 1000, // 5 minutes
    refetchInterval: POLL_STATIC || false,
  });
};

// ============= Sponsors =============

/** Fetch sponsor list - retry disabled since some tournaments have no sponsors */
export const useSponsors = () => {
  return useQuery<Sponsor[]>({
    queryKey: ['sponsors'],
    queryFn: () => apiFetch<Sponsor[]>(getSponsorsUrl()),
    staleTime: 30 * 60 * 1000, // 30 min cache - sponsors rarely change
    refetchInterval: false,     // No polling - fetch once per session
    retry: 0,                   // Don't retry if no sponsors exist
    refetchOnWindowFocus: false,
  });
};

// ============= Tournament Info =============

/** Fetch tournament general info */
export const useTournamentInfo = () => {
  return useQuery<TournamentInfo>({
    queryKey: ['tournament'],
    queryFn: async () => {
      const data = await apiFetch<any>(getTournamentUrl());
      return {
        ...data,
        logoUrl: data.logo || data.logoUrl || '',
        heroImageUrl: data.heroImage || data.heroImageUrl || '',
        logoHeaderUrl: data.logoHeader || data.logoHeaderUrl || '',
        phone: data.phone || '',
        email: data.email || '',
        city: data.city || '',
        state: data.state || '',
      };
    },
    staleTime: 5 * 60 * 1000,
    refetchInterval: POLL_STATIC || false,
  });
};

// ============= Tournament Stats =============

/**
 * Fetch tournament statistics, mapping API shape to TournamentStats.
 * Applies per-domain admin overrides from site_config.stats_config:
 *   - When an override field is a number it REPLACES the auto value.
 *   - When null/undefined the auto-computed value from the API is used.
 */
export const useTournamentStats = () => {
  /** Site config provides optional admin overrides (set in /admin → Stats tab) */
  const { data: siteConfig } = useSiteConfig();
  const overrides = siteConfig?.stats_config ?? null;

  return useQuery<TournamentStats>({
    queryKey: ['tournament-stats', overrides],
    queryFn: async () => {
      const data = await apiFetch<any>(getTournamentStatsUrl());

      /** Auto-computed values from the tournament endpoint */
      const autoYears = data?.stats?.yearsHistory ?? 0;
      const autoTotal = data?.stats?.totalHistoricalPlayers ?? 0;
      const autoMaxCat = data?.stats?.maxCategories ?? 0;

      /** Apply overrides when provided; otherwise keep auto values */
      const years = (overrides?.yearsHistory ?? null) !== null
        ? Number(overrides!.yearsHistory)
        : autoYears;
      const total = (overrides?.totalHistoricalPlayers ?? null) !== null
        ? Number(overrides!.totalHistoricalPlayers)
        : autoTotal;
      const maxCat = (overrides?.maxCategories ?? null) !== null
        ? Number(overrides!.maxCategories)
        : autoMaxCat;

      /** Round years down to nearest multiple of 2 for the "+" display */
      const rounded = Math.floor(years / 2) * 2;

      return {
        totalHistoricalPlayers: total,
        yearsHistory: years,
        yearsHistoryDisplay: `${rounded}+`,
        maxCategories: maxCat,
      };
    },
    staleTime: 2 * 60 * 1000,
    refetchInterval: POLL_STATIC || false,
  });
};

// ============= Eventos =============

/** Fetch event schedule */
export const useEventos = () => {
  return useQuery<EventDay[]>({
    queryKey: ['eventos'],
    queryFn: () => apiFetch<EventDay[]>(getEventosUrl()),
    staleTime: 5 * 60 * 1000,
    refetchInterval: POLL_STATIC || false,
  });
};
