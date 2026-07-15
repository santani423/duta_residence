import { createContext, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../services/estateApi.js';
import { useAuth } from './AuthContext.jsx';
import GuidedTour from '../components/help/GuidedTour.jsx';

const HelpCenterContext = createContext(null);

export function HelpCenterProvider({ children }) {
  const { token } = useAuth();
  const queryClient = useQueryClient();
  const toursQuery = useQuery({
    queryKey: ['guided-tours'],
    queryFn: api.guidedTours.list,
    enabled: Boolean(token),
    staleTime: 60000,
  });
  const tours = useMemo(() => toursQuery.data?.data || [], [toursQuery.data]);
  const [activeTourId, setActiveTourId] = useState(null);
  const autoStarted = useRef(false);
  const activeTour = tours.find((tour) => tour.id === activeTourId) || null;

  function startTour(moduleOrId) {
    const tour = typeof moduleOrId === 'number'
      ? tours.find((item) => item.id === moduleOrId)
      : tours.find((item) => item.module === moduleOrId);
    if (tour?.steps?.length) setActiveTourId(tour.id);
  }

  function closeTour() {
    setActiveTourId(null);
    queryClient.invalidateQueries({ queryKey: ['guided-tours'] });
  }

  function tourFor(moduleSlug) {
    return tours.find((tour) => tour.module === moduleSlug) || null;
  }

  // Auto-start ditunda lewat requestAnimationFrame supaya setState-nya tidak sinkron
  // langsung di badan effect (lihat catatan yang sama di GuidedTour.jsx).
  useEffect(() => {
    if (autoStarted.current || activeTourId || tours.length === 0) return undefined;
    const candidate = tours.find((tour) => tour.auto_start && !tour.progress && tour.steps?.length);
    if (!candidate) return undefined;

    const frame = requestAnimationFrame(() => {
      autoStarted.current = true;
      setActiveTourId(candidate.id);
    });

    return () => cancelAnimationFrame(frame);
  }, [tours, activeTourId]);

  return (
    <HelpCenterContext.Provider value={{ tours, startTour, tourFor, closeTour }}>
      {children}
      {activeTour ? <GuidedTour tour={activeTour} onClose={closeTour} /> : null}
    </HelpCenterContext.Provider>
  );
}

export function useHelpCenter() {
  return useContext(HelpCenterContext);
}
