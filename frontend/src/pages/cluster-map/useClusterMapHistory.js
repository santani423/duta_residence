import { useCallback, useReducer } from 'react';

const MAX_HISTORY = 50;

function createInitialState(objects) {
  return { past: [], present: objects, future: [] };
}

function reducer(state, action) {
  switch (action.type) {
    case 'COMMIT': {
      const next = typeof action.updater === 'function' ? action.updater(state.present) : action.updater;
      return { past: [...state.past, state.present].slice(-MAX_HISTORY), present: next, future: [] };
    }
    case 'UNDO': {
      if (!state.past.length) return state;
      const previous = state.past[state.past.length - 1];
      return { past: state.past.slice(0, -1), present: previous, future: [state.present, ...state.future] };
    }
    case 'REDO': {
      if (!state.future.length) return state;
      const [next, ...rest] = state.future;
      return { past: [...state.past, state.present], present: next, future: rest };
    }
    case 'REPLACE':
      return createInitialState(action.objects);
    default:
      return state;
  }
}

export function useClusterMapHistory(initialObjects = []) {
  const [state, dispatch] = useReducer(reducer, initialObjects, createInitialState);

  const commit = useCallback((updater) => dispatch({ type: 'COMMIT', updater }), []);
  const undo = useCallback(() => dispatch({ type: 'UNDO' }), []);
  const redo = useCallback(() => dispatch({ type: 'REDO' }), []);
  const replace = useCallback((objects) => dispatch({ type: 'REPLACE', objects }), []);

  return {
    objects: state.present,
    commit,
    undo,
    redo,
    replace,
    canUndo: state.past.length > 0,
    canRedo: state.future.length > 0,
  };
}
