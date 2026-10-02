/**
 * Salidas Page
 * Displays one mixed tee-time list per day.
 * Includes player search across all days.
 * Data fetched from salidas.php and salidas_det.php via React Query hooks
 */

import Layout from '@/components/layout/Layout';
import PageHero from '@/components/shared/PageHero';
import PlayerSearchInput from '@/components/shared/PlayerSearchInput';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowLeft, Calendar, Loader2, Search, Users } from 'lucide-react';
import { useState, useMemo } from 'react';
import { useQueries } from '@tanstack/react-query';
import { useSalidasMaster, useSalidasDetail } from '@/hooks/useSalidasData';
import type { SalidasDay, SalidasCategory, SalidasDetailResponse, SalidasGroup } from '@/hooks/useSalidasData';
import { apiFetch } from '@/lib/apiClient';
import { getSalidasDayUrl, POLL_ACTIVE } from '@/config/api';
import { ApiError } from '@/lib/apiClient';
import { normalizeSearchText, buildUniqueNameSuggestions } from '@/lib/searchUtils';
import salidasHero from '@/assets/salidas-hero.jpg';

// ============= Render helpers =============

/**
 * Calcula el total de renglones que ocupará un grupo en la tabla.
 * En PAREJAS cada jugador con `partner` ocupa 2 renglones (uno por integrante);
 * en INDIVIDUAL cada jugador ocupa 1. Se usa para los `rowSpan` de las columnas
 * compartidas (Hoyo / Hora) de un mismo grupo de salida.
 */
const countGroupRows = (players: SalidasGroup['players']): number =>
  (players ?? []).reduce((acc, p) => acc + (p.partner ? 2 : 1), 0);

/**
 * Detecta si un conjunto de jugadores corresponde a categoría de PAREJAS.
 * Se usa para decidir si la tabla muestra la columna "Equipo" (código de
 * grupo/pareja, p.ej. C05) y para ajustar el colSpan del footer.
 */
const hasAnyPair = (players: SalidasGroup['players']): boolean =>
  (players ?? []).some((p) => !!p.partner);

const groupsHaveAnyPair = (groups: SalidasGroup[] | undefined): boolean =>
  (groups ?? []).some((g) => hasAnyPair(g.players ?? []));

/**
 * La última columna muestra la CATEGORÍA de cada jugador (legacy
 * salidas_detsu.php → v_sal_jug.catjugador) cuando el backend la provee;
 * si no viene, se conserva el Score como antes.
 */
const hasAnyCategory = (players: SalidasGroup['players']): boolean =>
  (players ?? []).some((p) => !!p.category);

const groupsHaveAnyCategory = (groups: SalidasGroup[] | undefined): boolean =>
  (groups ?? []).some((g) => hasAnyCategory(g.players ?? []));

// ============= Search Result Type =============

/** Etiqueta TEE: prefiere el nombre del color (salidas.color); si no hay, el número de tee. */
const teeLabel = (tee?: string, color?: string): string => {
  if (color && color.trim()) return `TEE: ${color.trim()}`;
  if (tee && tee.trim()) return `TEE ${tee.trim()}`;
  return 'TEE POR DEFINIR';
};

/** Represents a player search match with full group context */
interface SearchResult {
  /** ISO date used to scope results to a selected normal departure day. */
  dayDate: string;
  /** Day display label */
  dayLabel: string;
  /** Course name */
  course: string;
  /** Category name */
  categoryName: string;
  /** Scoring system (e.g. Medal Play, Stableford) */
  system: string;
  /** Tee assignment */
  tee: string;
  /** The full group containing the matched player */
  group: SalidasGroup;
  /** Index of matched player within the group */
  matchedPlayerIdx: number;
}

// ============= Component =============

const Salidas = () => {
  /** Currently selected day index */
  const [selectedDayIdx, setSelectedDayIdx] = useState<number | null>(null);
  /** Currently selected caljgoid for detail view */
  const [selectedCaljgoid, setSelectedCaljgoid] = useState<string | null>(null);
  /** Selected category metadata for header display */
  const [selectedCatMeta, setSelectedCatMeta] = useState<SalidasCategory | null>(null);
  /** Player search query */
  const [searchQuery, setSearchQuery] = useState('');
  /**
   * Whether the search results UI should take over the day-selection screen.
   * Derived from searchQuery length (no separate boolean to avoid stale state).
   */
  const normalizedQuery = normalizeSearchText(searchQuery);
  const searchActive = normalizedQuery.length >= 2;

  // Fetch master data: days + categories
  const { data: master, isLoading: loadingMaster } = useSalidasMaster();
  const days = master?.days ?? [];
  const isNormalDeparture = master?.typeSalida === 0;

  /** Collect all caljgoids across all days for search queries */
  const allCategories = useMemo(() => {
    return days.flatMap((day) =>
      day.categories.map((cat) => ({
        caljgoid: String(cat.caljgoid),
        formato: cat.format?.toLowerCase().includes('pareja') ? 'parejas' : 'individual',
        dayDate: day.date,
        dayLabel: day.dateFormatted,
        course: cat.course || day.course,
      }))
    );
  }, [days]);

  /**
   * Fetch ALL category details in parallel as soon as we know the categories.
   * We need this data both for the autocomplete suggestions AND for the
   * search results — fetching only on-demand caused inconsistent results
   * because suggestions never populated until after typing started, and
   * late-arriving fetches were ignored by the UI.
   *
   * IMPORTANT: This MUST use a different queryKey than `useSalidasDetail`
   * because it stores a WRAPPED object ({ ...cat, detail }) rather than the
   * raw SalidasDetailResponse. Sharing the key would corrupt the cache used
   * by the detail view, causing it to render with `detail.groups === undefined`.
   */
  const searchQueries = useQueries({
    queries: allCategories.length > 0
      ? allCategories.map((cat) => ({
          queryKey: ['salidas-search', cat.caljgoid, cat.formato],
          queryFn: async () => {
            const data = await apiFetch<any>(getSalidasDayUrl(cat.caljgoid, cat.formato));
            return {
              ...cat,
              detail: {
                caljgoid: data?.caljgoid ?? cat.caljgoid,
                date: data?.date ?? '',
                course: data?.course ?? cat.course,
                categoryId: data?.categoryId ?? '',
                categoryName: data?.categoryName ?? '',
                shortName: data?.shortName ?? '',
                system: data?.system ?? '',
                tee: data?.tee ?? '',
                teeColor: data?.teeColor ?? '',
                groups: Array.isArray(data?.groups) ? data.groups : [],
              } as SalidasDetailResponse,
            };
          },
          staleTime: POLL_ACTIVE,
        }))
      : [],
  });

  /** Filter search results based on query (whitespace/accent tolerant) */
  const searchResults = useMemo<SearchResult[]>(() => {
    if (normalizedQuery.length < 2) return [];

    const results: SearchResult[] = [];
    for (const query of searchQueries) {
      if (!query.data?.detail) continue;
      const { dayDate, dayLabel, course, detail } = query.data;
      for (const group of (detail.groups ?? [])) {
        const players = group.players ?? [];
        const matchIdx = players.findIndex((p) =>
          normalizeSearchText(p.name).includes(normalizedQuery)
        );
        if (matchIdx !== -1) {
          results.push({
            dayDate,
            dayLabel,
            course,
            categoryName: detail.categoryName,
            system: detail.system,
            tee: detail.tee,
            group,
            matchedPlayerIdx: matchIdx,
          });
        }
      }
    }
    return results;
  }, [normalizedQuery, searchQueries]);

  const visibleSearchResults = useMemo(
    () => selectedDayIdx === null
      ? searchResults
      : searchResults.filter((result) => result.dayDate === days[selectedDayIdx]?.date),
    [searchResults, selectedDayIdx, days],
  );

  /**
   * Build unique player-name suggestions from already-loaded data.
   * Salidas fetches detail only when search is active, so suggestions populate
   * progressively as queries resolve.
   */
  const playerSuggestions = useMemo(() => {
    const allNames: string[] = [];
    for (const query of searchQueries) {
      if (!query.data?.detail) continue;
      for (const group of (query.data.detail.groups ?? [])) {
        for (const p of (group.players ?? [])) {
          if (p?.name) allNames.push(p.name);
        }
      }
    }
    return buildUniqueNameSuggestions(allNames);
  }, [searchQueries]);

  /**
   * True only while NO query has resolved yet. We intentionally avoid
   * `some(isLoading)` — that would block the UI even when most days have
   * already loaded, hiding partial matches the user could already see.
   */
  const searchLoading = searchActive && searchQueries.length > 0 && searchQueries.every((q) => q.isLoading);

  /** Count of failed search queries — used to surface silent fetch failures
      that would otherwise hide a player's tee time on a specific day. */
  const searchFailures = useMemo(() => {
    const failed = searchQueries.filter((q) => q.isError);
    if (failed.length > 0) {
      // Log details to console so the developer can see which day/category failed
      // eslint-disable-next-line no-console
      console.warn('[Salidas search] Some category fetches failed:', failed.map((q, i) => ({
         category: allCategories[i],
         error: q.error,
      })).filter((x) => x.error));
    }
    return failed.length;
  }, [searchQueries, allCategories]);

  /** Normalize selected format to endpoint-compatible values */
  const selectedFormato = selectedCatMeta?.format?.toLowerCase().includes('pareja') ? 'parejas' : 'individual';

  // Fetch detail for selected category
  const {
    data: detail,
    isLoading: loadingDetail,
    isError: detailIsError,
    error: detailError,
  } = useSalidasDetail(selectedCaljgoid, selectedFormato);

  /** Currently selected day object */
  const selectedDay: SalidasDay | null = selectedDayIdx !== null ? days[selectedDayIdx] : null;

  /** Unique departures open directly; normal departures first select a category. */
  const handleDayClick = (dayIdx: number) => {
    const day = days[dayIdx];
    const departure = day.categories[0];
    setSelectedDayIdx(dayIdx);
    if (isNormalDeparture) {
      setSelectedCaljgoid(null);
      setSelectedCatMeta(null);
    } else {
      setSelectedCaljgoid(departure ? String(departure.caljgoid) : null);
      setSelectedCatMeta(departure ?? null);
    }
  };

  const handleCategoryClick = (category: SalidasCategory) => {
    setSelectedCaljgoid(String(category.caljgoid));
    setSelectedCatMeta(category);
    setSearchQuery('');
  };

  /** Handle back navigation */
  const handleBack = () => {
    if (selectedCaljgoid) {
      setSelectedCaljgoid(null);
      setSelectedCatMeta(null);
      if (!isNormalDeparture) setSelectedDayIdx(null);
    } else {
      setSelectedDayIdx(null);
    }
  };

  /** Clear search and return to normal view */
  const handleClearSearch = () => {
    setSearchQuery('');
  };

  return (
    <Layout>
      <PageHero
        title="Salidas"
        subtitle="Horarios de salida y grupos de juego"
        backgroundImage={salidasHero}
      />
      <section className="py-16 bg-background">
        <div className="container mx-auto px-4">

          {/* ============= Level 1: Day Selection ============= */}
          {selectedDayIdx === null ? (
            <>
              <div className="text-center mb-10">
                <h2 className="text-3xl font-bold text-foreground">
                  DÍAS DE JUEGO: <span className="text-primary">{loadingMaster ? '…' : days.length}</span>
                </h2>
              </div>

              {/* ============= Player Search Bar (with autocomplete) =============
                  Always rendered at day-selection level (mirrors Competición).
                  Visible even while master data is loading or when no days exist. */}
              <PlayerSearchInput
                className="max-w-md mx-auto mb-8"
                value={searchQuery}
                onChange={setSearchQuery}
                suggestions={playerSuggestions}
              />

              {/* ============= Search Results ============= */}
              {searchActive && searchQuery.trim().length >= 2 ? (
                <div className="max-w-5xl mx-auto">
                  {searchLoading ? (
                    <div className="flex justify-center py-12">
                      <Loader2 className="h-8 w-8 animate-spin text-primary" />
                    </div>
                  ) : visibleSearchResults.length === 0 ? (
                    <div className="text-center py-12">
                      <Search className="h-10 w-10 mx-auto mb-3 text-muted-foreground/50" />
                      <p className="text-muted-foreground">No se encontró ningún jugador con "{searchQuery}"</p>
                    </div>
                  ) : (
                    <div className="space-y-6">
                      <p className="text-sm text-muted-foreground text-center mb-4">
                        {visibleSearchResults.length} grupo{visibleSearchResults.length !== 1 ? 's' : ''} encontrado{visibleSearchResults.length !== 1 ? 's' : ''}
                      </p>
                      {searchFailures > 0 && (
                        <p className="text-sm text-destructive text-center mb-2">
                          ⚠️ {searchFailures} día(s)/categoría(s) no se pudieron cargar — algunos resultados pueden faltar. Revisa la consola.
                        </p>
                      )}
                      {visibleSearchResults.map((result, rIdx) => (
                        <Card key={rIdx} className="border-border/50 bg-white">
                          <CardContent className="p-0 bg-white">
                            {/* Result context header */}
                            <div className="bg-muted/50 px-4 py-2 border-b border-border/30 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                              <span className="font-semibold text-foreground capitalize">{result.dayLabel}</span>
                              <span className="text-muted-foreground">{result.course}</span>
                              <span className="text-primary font-medium">{result.categoryName || 'Grupos de Juego'}</span>
                            </div>
                            {/* Group table */}
                            <div className="overflow-x-auto bg-white">
                              <Table className="bg-white tournament-table">
                                <TableHeader>
                                  <TableRow className="bg-primary hover:bg-primary">
                                    <TableHead className="text-primary-foreground font-bold text-center w-20">Hoyo</TableHead>
                                    <TableHead className="text-primary-foreground font-bold text-center w-20">Hora</TableHead>
                                    {hasAnyPair(result.group.players ?? []) && (
                                      <TableHead className="text-primary-foreground font-bold text-center w-20">Equipo</TableHead>
                                    )}
                                    <TableHead className="text-primary-foreground font-bold text-center w-16">Club</TableHead>
                                    <TableHead className="text-primary-foreground font-bold">Jugador</TableHead>
                                    <TableHead className="text-primary-foreground font-bold text-center w-24">
                                      {hasAnyCategory(result.group.players ?? []) ? 'Categoría' : 'Score'}
                                    </TableHead>
                                  </TableRow>
                                </TableHeader>
                                <TableBody>
                                  {(() => {
                                    /* En PAREJAS cada jugador se renderiza como 2 renglones (uno por integrante)
                                     * y la celda de Score abarca ambos con rowSpan=2 para quedar centrada. */
                                    const players = result.group.players ?? [];
                                    const totalRows = countGroupRows(players);
                                    const showTeam = hasAnyPair(players);
                                    let firstRowEmitted = false;
                                    const rows: JSX.Element[] = [];
                                    players.forEach((player, pIdx) => {
                                      const isPair = !!player.partner;
                                      const isMatched = pIdx === result.matchedPlayerIdx;
                                      const renderHoleHora = !firstRowEmitted;
                                      firstRowEmitted = true;
                                      // ----- Renglón principal (jugador 1) -----
                                      rows.push(
                                        <TableRow
                                          key={`${pIdx}-a`}
                                          /* `border-b-0` en el primer renglón de una pareja para que no aparezca
                                           * una línea entre los dos integrantes del mismo equipo. */
                                          className={`bg-white hover:bg-white ${isMatched ? 'bg-primary/5 hover:bg-primary/5' : ''} ${isPair ? 'border-b-0' : ''}`}
                                        >
                                          {renderHoleHora ? (
                                            <>
                                              <TableCell className="text-center font-bold text-base text-foreground" rowSpan={totalRows}>
                                                {result.group.tee}
                                              </TableCell>
                                              <TableCell className="text-center font-bold text-base text-foreground" rowSpan={totalRows}>
                                                {result.group.time}
                                              </TableCell>
                                            </>
                                          ) : null}
                                          {showTeam && (
                                            /* Código de pareja/equipo (p.ej. C05). Abarca ambos renglones
                                             * de la pareja con rowSpan=2 para que se centre verticalmente. */
                                            <TableCell className="text-center font-bold text-foreground align-middle" rowSpan={isPair ? 2 : 1}>
                                              {player.groupId || '—'}
                                            </TableCell>
                                          )}
                                          <TableCell className="p-1 text-center align-middle">
                                            {player.clubLogo ? (
                                              <img src={player.clubLogo} alt="Club" className="w-auto object-contain rounded inline-block" style={{ height: '2.1375rem' }} onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }} />
                                            ) : (<span className="text-xs text-muted-foreground">—</span>)}
                                          </TableCell>
                                          <TableCell className={`font-medium player-name-cell ${isMatched ? 'text-primary font-bold' : 'text-foreground'}`}>
                                            {/* Recorte a 4 renglones en móvil (.player-name-clamp) */}
                                            <span className="player-name-clamp">{player.name}</span>
                                          </TableCell>
                                          {/* Score: en parejas se centra entre los dos renglones (rowSpan=2). */}
                                          <TableCell className="text-center font-bold text-primary align-middle" rowSpan={isPair ? 2 : 1}>
                                            {player.category || player.score || '—'}
                                          </TableCell>
                                        </TableRow>
                                      );
                                      // ----- Renglón secundario (jugador 2) si es pareja -----
                                      if (isPair) {
                                        rows.push(
                                          <TableRow
                                            key={`${pIdx}-b`}
                                            className={`bg-white hover:bg-white ${isMatched ? 'bg-primary/5 hover:bg-primary/5' : ''}`}
                                          >
                                            <TableCell className="p-1 text-center align-middle">
                                              {player.clubLogo2 ? (
                                                <img src={player.clubLogo2} alt="Club" className="w-auto object-contain rounded inline-block" style={{ height: '2.1375rem' }} onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }} />
                                              ) : (<span className="text-xs text-muted-foreground">—</span>)}
                                            </TableCell>
                                            <TableCell className={`font-medium player-name-cell ${isMatched ? 'text-primary font-bold' : 'text-foreground'}`}>
                                              <span className="player-name-clamp">{player.partner}</span>
                                            </TableCell>
                                          </TableRow>
                                        );
                                      }
                                    });
                                    return rows;
                                  })()}
                                </TableBody>
                                {/* Footer with category name */}
                                <tfoot>
                                  <tr className="bg-primary">
                                    <td
                                      colSpan={hasAnyPair(result.group.players ?? []) ? 6 : 5}
                                      className="text-primary-foreground font-bold text-center py-2 text-sm"
                                    >
                                      GRUPOS DE JUEGO
                                    </td>
                                  </tr>
                                </tfoot>
                              </Table>
                            </div>
                          </CardContent>
                        </Card>
                      ))}
                    </div>
                  )}
                </div>
              ) : (
                /* ============= Day Cards Grid ============= */
                <>
                  {loadingMaster ? (
                    <div className="flex justify-center py-12">
                      <Loader2 className="h-8 w-8 animate-spin text-primary" />
                    </div>
                  ) : days.length === 0 ? (
                    <div className="text-center py-16">
                      <Calendar className="h-12 w-12 mx-auto mb-4 text-muted-foreground/50" />
                      <p className="text-muted-foreground text-lg">No hay salidas disponibles</p>
                    </div>
                  ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 max-w-4xl mx-auto">
                      {days.map((day, idx) => (
                        <Card
                          key={idx}
                          className="border-border/50 hover:border-primary/50 transition-all hover:shadow-lg cursor-pointer"
                          onClick={() => handleDayClick(idx)}
                        >
                          <CardContent className="p-6 text-center">
                            <Calendar className="h-8 w-8 mx-auto mb-3 text-primary" />
                            <h3 className="font-bold text-foreground text-lg mb-1 capitalize">{day.dateFormatted}</h3>
                            <p className="text-muted-foreground text-sm mb-3">{day.course}</p>
                  <p className="text-sm font-medium text-primary">
                    {isNormalDeparture ? `${day.categories.length} categorías` : 'Grupos de Juego'}
                  </p>
                          </CardContent>
                        </Card>
                      ))}
                    </div>
                  )}
                </>
              )}
            </>

          /* ============= Level 2: Category Selection (normal departures) ============= */
          ) : isNormalDeparture && selectedCaljgoid === null ? (
            <>
              <Button variant="ghost" onClick={handleBack} className="mb-6 gap-2 bg-primary/10 hover:bg-primary/20">
                <ArrowLeft className="h-4 w-4" />
                Volver a días
              </Button>

              <div className="mb-8 text-center">
                <h2 className="font-serif text-3xl font-bold capitalize text-foreground">
                  {selectedDay?.dateFormatted}
                </h2>
                <p className="mt-2 text-lg text-muted-foreground">Selecciona una categoría</p>
              </div>

              <PlayerSearchInput
                className="max-w-md mx-auto mb-8"
                value={searchQuery}
                onChange={setSearchQuery}
                suggestions={playerSuggestions}
              />

              {searchActive ? (
                <div className="max-w-5xl mx-auto">
                  {searchLoading ? (
                    <div className="flex justify-center py-12"><Loader2 className="h-8 w-8 animate-spin text-primary" /></div>
                  ) : visibleSearchResults.length === 0 ? (
                    <div className="text-center py-12">
                      <Search className="h-10 w-10 mx-auto mb-3 text-muted-foreground/50" />
                      <p className="text-muted-foreground">No se encontró ningún jugador con "{searchQuery}" en esta fecha</p>
                    </div>
                  ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                      {visibleSearchResults.map((result, index) => (
                        <Card key={`${result.group.id}-${index}`} className="border-border/50 bg-card">
                          <CardContent className="p-5">
                            <p className="font-bold text-foreground">{result.group.players[result.matchedPlayerIdx]?.name}</p>
                            <p className="mt-1 text-sm text-primary">{result.categoryName}</p>
                            <p className="text-sm text-muted-foreground">{result.course}</p>
                            <p className="mt-2 text-sm text-foreground">Hoyo {result.group.tee} · {result.group.time}</p>
                          </CardContent>
                        </Card>
                      ))}
                    </div>
                  )}
                </div>
              ) : (
                <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 max-w-6xl mx-auto">
                  {(selectedDay?.categories ?? []).map((category) => (
                    <Card
                      key={`${category.caljgoid}-${category.categoryId}`}
                      className="border-border/50 hover:border-primary/50 transition-all hover:shadow-md cursor-pointer bg-card"
                      onClick={() => handleCategoryClick(category)}
                    >
                      <CardContent className="p-4 sm:p-6 text-center min-h-36 sm:min-h-44 flex flex-col items-center justify-center">
                        <Users className="h-6 w-6 sm:h-7 sm:w-7 mb-2 sm:mb-3 text-primary" />
                        <h3 className="font-serif text-lg sm:text-xl font-bold text-foreground">{category.categoryName}</h3>
                        <p className="mt-1 text-xs sm:text-sm font-medium uppercase text-muted-foreground">{teeLabel(category.tee, category.teeColor)}</p>
                        <p className="mt-2 text-xs sm:text-sm text-muted-foreground">{category.course || selectedDay?.course}</p>
                        <p className="mt-2 sm:mt-3 text-sm text-foreground"><strong>{category.groupCount}</strong> grupo{category.groupCount === 1 ? '' : 's'}</p>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              )}
            </>

          /* ============= Level 3: Groups Table ============= */
          ) : (
            <>
              <Button variant="ghost" onClick={handleBack} className="mb-6 gap-2 bg-primary/10 hover:bg-primary/20">
                <ArrowLeft className="h-4 w-4" />
                 Volver a días
              </Button>

              {loadingDetail ? (
                <div className="flex justify-center py-12">
                  <Loader2 className="h-8 w-8 animate-spin text-primary" />
                </div>
              ) : detail ? (
                <>
                  {/* Header: left-aligned on mobile, centered on desktop */}
                  <div className="mb-8 text-left md:text-center">
                    <h2 className="text-3xl font-bold text-foreground mb-1">
                      {isNormalDeparture ? selectedCatMeta?.categoryName : 'Grupos de Juego'}
                    </h2>
                    <p className="text-muted-foreground text-lg">{detail.course || selectedCatMeta?.course}</p>
                    {isNormalDeparture && (selectedCatMeta?.tee || selectedCatMeta?.teeColor) ? (
                      <p className="text-sm font-medium uppercase text-primary mt-1">{teeLabel(selectedCatMeta?.tee, selectedCatMeta?.teeColor)}</p>
                    ) : null}
                    <p className="text-muted-foreground text-lg">{selectedDay?.dateFormatted}</p>
                    <p className="text-sm text-muted-foreground mt-1">
                      {(detail.groups ?? []).length} grupos
                    </p>
                  </div>

                  {(detail.groups ?? []).length === 0 ? (
                    <div className="text-center py-16">
                      <Users className="h-12 w-12 mx-auto mb-4 text-muted-foreground/50" />
                       <p className="text-muted-foreground text-lg">No hay grupos de salida para este día</p>
                    </div>
                  ) : (
                    <Card className="border-border/50 bg-white max-w-5xl mx-auto">
                      <CardContent className="p-0 bg-white">
                        <div className="overflow-x-auto bg-white">
                          <Table className="bg-white tournament-table">
                            <TableHeader>
                              <TableRow className="bg-primary hover:bg-primary">
                                <TableHead className="text-primary-foreground font-bold text-center w-20">Hoyo</TableHead>
                                <TableHead className="text-primary-foreground font-bold text-center w-20">Hora</TableHead>
                                {groupsHaveAnyPair(detail.groups) && (
                                  <TableHead className="text-primary-foreground font-bold text-center w-20">Equipo</TableHead>
                                )}
                                <TableHead className="text-primary-foreground font-bold text-center w-16">Club</TableHead>
                                <TableHead className="text-primary-foreground font-bold">Jugador</TableHead>
                                <TableHead className="text-primary-foreground font-bold text-center w-24">
                                  {groupsHaveAnyCategory(detail.groups) ? 'Categoría' : 'Score'}
                                </TableHead>
                              </TableRow>
                            </TableHeader>
                            <TableBody>
                              {(detail.groups ?? []).map((group, gIdx) => {
                                /* Igual que el bloque de búsqueda: parejas → 2 renglones por jugador.
                                 * Hoyo/Hora abarcan TODOS los renglones del grupo;
                                 * Score abarca los 2 renglones de cada pareja. */
                                const players = group.players ?? [];
                                const totalRows = countGroupRows(players);
                                const showTeam = groupsHaveAnyPair(detail.groups);
                                const isLastGroup = gIdx >= (detail.groups ?? []).length - 1;
                                let firstRowEmitted = false;
                                const rows: JSX.Element[] = [];
                                players.forEach((player, pIdx) => {
                                  const isPair = !!player.partner;
                                  const isLastPlayer = pIdx === players.length - 1;
                                  const renderHoleHora = !firstRowEmitted;
                                  firstRowEmitted = true;
                                  // Separador entre grupos: aplica sólo al ÚLTIMO renglón del último jugador.
                                  const separatorRow2 = isPair && isLastPlayer && !isLastGroup ? 'border-b-2 border-primary/20' : '';
                                  const separatorRow1 = !isPair && isLastPlayer && !isLastGroup ? 'border-b-2 border-primary/20' : '';
                                  // ----- Renglón principal -----
                                  rows.push(
                                    <TableRow
                                      key={`${group.id}-${pIdx}-a`}
                                      /* `border-b-0` cuando es pareja: oculta la línea divisoria por defecto
                                       * entre los dos integrantes del mismo equipo. */
                                      className={`bg-white hover:bg-white ${separatorRow1} ${isPair ? 'border-b-0' : ''}`}
                                    >
                                      {renderHoleHora ? (
                                        <>
                                          <TableCell className="text-center font-bold text-base text-foreground" rowSpan={totalRows}>
                                            {group.tee}
                                          </TableCell>
                                          <TableCell className="text-center font-bold text-base text-foreground" rowSpan={totalRows}>
                                            {group.time}
                                          </TableCell>
                                        </>
                                      ) : null}
                                      {showTeam && (
                                        /* Columna "Equipo": código de pareja/grupo (p.ej. C05).
                                         * rowSpan=2 cuando hay pareja para centrar verticalmente. */
                                        <TableCell className="text-center font-bold text-foreground align-middle" rowSpan={isPair ? 2 : 1}>
                                          {player.groupId || '—'}
                                        </TableCell>
                                      )}
                                      <TableCell className="p-1 text-center align-middle">
                                        {player.clubLogo ? (
                                          <img src={player.clubLogo} alt="Club" className="w-auto object-contain rounded inline-block" style={{ height: '2.1375rem' }} onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }} />
                                        ) : (<span className="text-xs text-muted-foreground">—</span>)}
                                      </TableCell>
                                      <TableCell className="font-medium text-foreground player-name-cell">
                                        {/* Recorte a 4 renglones en móvil (.player-name-clamp) */}
                                        <span className="player-name-clamp">{player.name}</span>
                                      </TableCell>
                                      <TableCell className="text-center font-bold text-primary align-middle" rowSpan={isPair ? 2 : 1}>
                                        {player.category || player.score || '—'}
                                      </TableCell>
                                    </TableRow>
                                  );
                                  // ----- Renglón secundario (segundo integrante de la pareja) -----
                                  if (isPair) {
                                    rows.push(
                                      <TableRow key={`${group.id}-${pIdx}-b`} className={`bg-white hover:bg-white ${separatorRow2}`}>
                                        <TableCell className="p-1 text-center align-middle">
                                          {player.clubLogo2 ? (
                                            <img src={player.clubLogo2} alt="Club" className="w-auto object-contain rounded inline-block" style={{ height: '2.1375rem' }} onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }} />
                                          ) : (<span className="text-xs text-muted-foreground">—</span>)}
                                        </TableCell>
                                        <TableCell className="font-medium text-foreground player-name-cell">
                                          <span className="player-name-clamp">{player.partner}</span>
                                        </TableCell>
                                      </TableRow>
                                    );
                                  }
                                });
                                return rows;
                              })}
                            </TableBody>
                            {/* Footer row repeating category name */}
                            <tfoot>
                              <tr className="bg-primary">
                                <td
                                  colSpan={groupsHaveAnyPair(detail.groups) ? 6 : 5}
                                  className="text-primary-foreground font-bold text-center py-2 text-sm"
                                >
                                  {isNormalDeparture ? selectedCatMeta?.categoryName : 'GRUPOS DE JUEGO'}
                                </td>
                              </tr>
                            </tfoot>
                          </Table>
                        </div>
                      </CardContent>
                    </Card>
                  )}
                </>
              ) : detailIsError ? (
                <div className="text-center py-16">
                  <p className="text-muted-foreground mb-2">Error al cargar los datos</p>
                  <p className="text-xs text-muted-foreground break-all">
                    {detailError instanceof ApiError
                      ? `${detailError.status} · ${detailError.message}`
                      : 'Error desconocido'}
                  </p>
                  {detailError instanceof ApiError && detailError.endpoint.includes('debug=1') ? (
                    <pre className="mt-4 text-left text-[11px] leading-5 text-muted-foreground bg-muted p-3 rounded-md overflow-auto max-w-4xl mx-auto whitespace-pre-wrap break-all">
                      {JSON.stringify(
                        (detailError.responseData as { _debug?: unknown; _debug_queries?: unknown })?._debug ??
                          (detailError.responseData as { _debug_queries?: unknown })?._debug_queries ??
                          detailError.responseData ??
                          detailError.responseBody ??
                          null,
                        null,
                        2
                      )}
                    </pre>
                  ) : null}
                </div>
              ) : (
                <div className="text-center py-16">
                  <p className="text-muted-foreground">Sin datos para la categoría seleccionada</p>
                </div>
              )}
            </>
          )}
        </div>
      </section>
    </Layout>
  );
};

export default Salidas;
