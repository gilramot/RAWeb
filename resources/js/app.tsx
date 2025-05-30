import { createInertiaApp } from '@inertiajs/react';
import * as Sentry from '@sentry/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot, hydrateRoot } from 'react-dom/client';

import { AppProviders } from './common/components/AppProviders';
import type { AppGlobalProps } from './common/models';
import { loadDayjsLocale } from './common/utils/l10n/loadDayjsLocale';
import i18n from './i18n-client';
// @ts-expect-error -- this isn't a real ts module
import { Ziggy } from './ziggy';

// @ts-expect-error -- we're injecting this on purpose
globalThis.Ziggy = Ziggy;

const appName = import.meta.env.APP_NAME || 'RetroAchievements';

// Initialize Sentry.
Sentry.init({
  dsn: import.meta.env.VITE_SENTRY_DSN,
  integrations: [
    Sentry.browserTracingIntegration(),
    Sentry.replayIntegration({
      maskAllText: false,
      blockAllMedia: false,
    }),
    Sentry.captureConsoleIntegration({ levels: ['warn', 'error'] }),
  ],
  environment: import.meta.env.APP_ENV,
  release: import.meta.env.APP_VERSION,
  tracesSampleRate: import.meta.env.SENTRY_TRACES_SAMPLE_RATE,
  tracePropagationTargets: ['localhost', /^https:\/\/retroachievements\.org\/internal-api/],
  replaysSessionSampleRate: import.meta.env.SENTRY_REPLAYS_SESSION_SAMPLE_RATE,
  replaysOnErrorSampleRate: 1.0,
});

createInertiaApp({
  title: (title) => (title ? `${title} · ${appName}` : appName),

  resolve: (name) =>
    resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),

  // @ts-expect-error -- async setup() breaks type rules, but is actually supported.
  async setup({ el, App, props }) {
    const globalProps = props.initialPage.props as unknown as AppGlobalProps;
    const userLocale = globalProps.auth?.user.locale ?? 'en_US';

    if (globalProps.auth?.user) {
      Sentry.setUser({
        id: globalProps.auth.user.id,
        username: globalProps.auth.user.displayName,
      });
    }

    await Promise.all([i18n.changeLanguage(userLocale), loadDayjsLocale(userLocale)]);

    const appElement = (
      <AppProviders i18n={i18n}>
        <App {...props} />
      </AppProviders>
    );

    if (import.meta.env.DEV) {
      createRoot(el).render(appElement);

      return;
    }

    hydrateRoot(el, appElement);
  },

  progress: {
    delay: 250,
    color: '#29d',
  },
});
