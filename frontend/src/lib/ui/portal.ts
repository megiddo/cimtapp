/** Moves the element under `document.body` so fixed modals sit above the tab bar. */
export function portal(node: HTMLElement, target: string | HTMLElement = 'body'): { destroy: () => void } {
  const host = typeof target === 'string' ? document.querySelector(target) : target;
  if (host === null) {
    return { destroy: () => {} };
  }
  host.appendChild(node);
  return {
    destroy() {
      if (node.parentNode === host) {
        host.removeChild(node);
      }
    }
  };
}