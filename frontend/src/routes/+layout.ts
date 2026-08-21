import { redirect } from '@sveltejs/kit';
import { probeSession, shouldRedirectToHome, shouldRedirectToLogin } from '$lib/session';
import type { LayoutLoad } from './$types';

export const ssr = false;
export const prerender = false;

export const load: LayoutLoad = async ({ url, fetch }) => {
  const probe = await probeSession(fetch);
  if (shouldRedirectToLogin(url.pathname, probe.authenticated)) {
    redirect(302, '/login');
  }
  if (shouldRedirectToHome(url.pathname, probe.authenticated)) {
    redirect(302, '/');
  }

  return { me: probe.me };
};
