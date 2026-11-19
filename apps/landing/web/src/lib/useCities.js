import { useEffect, useRef, useState } from 'react';
import { fetchCities } from './api.js';

export function useCitySuggestions(country, text) {
  const [cities, setCities] = useState([]);
  const available = useRef(true);

  useEffect(() => {
    const query = text.trim();
    if (!country || query.length < 2 || !available.current) {
      setCities([]);
      return undefined;
    }

    const controller = new AbortController();
    const timer = setTimeout(async () => {
      const result = await fetchCities(country, query, controller.signal);
      if (controller.signal.aborted) return;
      if (!result.available) available.current = false; // service désactivé : on arrête d'interroger
      setCities(result.cities);
    }, 250);

    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [country, text]);

  return cities;
}