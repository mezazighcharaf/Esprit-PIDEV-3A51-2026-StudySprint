/* ============================================
   STUDYSPRINT — UI INTERACTIONS
   Vanilla JS: Drawer, Toast, Tabs, Dropdown
   ============================================ */

(function() {
  'use strict';

  // ─── DRAWER ───
  const Drawer = {
    init() {
      document.addEventListener('click', (e) => {
        // Open drawer
        const openTrigger = e.target.closest('[data-drawer-open]');
        if (openTrigger) {
          const drawerId = openTrigger.dataset.drawerOpen;
          this.open(drawerId);
          return;
        }

        // Close drawer
        const closeTrigger = e.target.closest('[data-drawer-close]');
        if (closeTrigger) {
          this.closeAll();
          return;
        }

        // Click on backdrop
        if (e.target.classList.contains('drawer-backdrop')) {
          this.closeAll();
        }
      });

      // ESC key closes drawer
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          this.closeAll();
        }
      });
    },

    open(drawerId) {
      const drawer = document.getElementById(drawerId);
      const backdrop = document.getElementById(drawerId + '-backdrop') ||
                       document.querySelector('.drawer-backdrop');

      if (drawer) {
        drawer.classList.add('open');
        document.body.style.overflow = 'hidden';

        if (backdrop) {
          backdrop.classList.add('open');
        }

        // Focus first focusable element
        const focusable = drawer.querySelector('button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusable) {
          setTimeout(() => focusable.focus(), 100);
        }
      }
    },

    close(drawerId) {
      const drawer = document.getElementById(drawerId);
      const backdrop = document.getElementById(drawerId + '-backdrop') ||
                       document.querySelector('.drawer-backdrop');

      if (drawer) {
        drawer.classList.remove('open');
        document.body.style.overflow = '';

        if (backdrop) {
          backdrop.classList.remove('open');
        }
      }
    },

    closeAll() {
      document.querySelectorAll('.drawer.open').forEach(drawer => {
        drawer.classList.remove('open');
      });
      document.querySelectorAll('.drawer-backdrop.open').forEach(backdrop => {
        backdrop.classList.remove('open');
      });
      document.body.style.overflow = '';
    }
  };

  // ─── TOAST ───
  const Toast = {
    container: null,

    init() {
      // Create container if not exists
      if (!document.querySelector('.toast-container')) {
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
      } else {
        this.container = document.querySelector('.toast-container');
      }

      // Handle close clicks
      document.addEventListener('click', (e) => {
        const closeTrigger = e.target.closest('.toast-close');
        if (closeTrigger) {
          const toast = closeTrigger.closest('.toast');
          if (toast) {
            this.dismiss(toast);
          }
        }
      });
    },

    show(options = {}) {
      const {
        type = 'info',
        title = '',
        message = '',
        duration = 5000
      } = options;

      const icons = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
      };

      const toast = document.createElement('div');
      toast.className = `toast toast-${type}`;
      toast.innerHTML = `
        <span class="toast-icon">${icons[type]}</span>
        <div class="toast-content">
          ${title ? `<div class="toast-title">${title}</div>` : ''}
          ${message ? `<div class="toast-message">${message}</div>` : ''}
        </div>
        <button class="toast-close" aria-label="Fermer">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      `;

      this.container.appendChild(toast);

      // Auto dismiss
      if (duration > 0) {
        setTimeout(() => this.dismiss(toast), duration);
      }

      return toast;
    },

    dismiss(toast) {
      toast.classList.add('toast-out');
      setTimeout(() => toast.remove(), 200);
    },

    success(title, message) {
      return this.show({ type: 'success', title, message });
    },

    error(title, message) {
      return this.show({ type: 'error', title, message });
    },

    warning(title, message) {
      return this.show({ type: 'warning', title, message });
    },

    info(title, message) {
      return this.show({ type: 'info', title, message });
    }
  };

  // ─── TABS ───
  const Tabs = {
    init() {
      document.addEventListener('click', (e) => {
        const tab = e.target.closest('[data-tab]');
        if (tab) {
          const tabGroup = tab.closest('[data-tabs]');
          const tabId = tab.dataset.tab;

          if (tabGroup) {
            this.activate(tabGroup, tabId);
          }
        }
      });

      // Keyboard navigation
      document.addEventListener('keydown', (e) => {
        const tab = e.target.closest('[data-tab]');
        if (!tab) return;

        const tabGroup = tab.closest('[data-tabs]');
        if (!tabGroup) return;

        const tabs = Array.from(tabGroup.querySelectorAll('[data-tab]'));
        const currentIndex = tabs.indexOf(tab);

        let newIndex;
        if (e.key === 'ArrowRight') {
          newIndex = (currentIndex + 1) % tabs.length;
        } else if (e.key === 'ArrowLeft') {
          newIndex = (currentIndex - 1 + tabs.length) % tabs.length;
        } else {
          return;
        }

        e.preventDefault();
        tabs[newIndex].focus();
        this.activate(tabGroup, tabs[newIndex].dataset.tab);
      });
    },

    activate(tabGroup, tabId) {
      // Update tab buttons
      tabGroup.querySelectorAll('[data-tab]').forEach(t => {
        t.classList.toggle('active', t.dataset.tab === tabId);
        t.setAttribute('aria-selected', t.dataset.tab === tabId);
      });

      // Update tab content
      const contentContainer = document.querySelector(`[data-tab-content="${tabGroup.dataset.tabs}"]`) ||
                               tabGroup.parentElement;

      if (contentContainer) {
        contentContainer.querySelectorAll('[data-tab-panel]').forEach(panel => {
          panel.classList.toggle('active', panel.dataset.tabPanel === tabId);
        });
      }
    }
  };

  // ─── DROPDOWN ───
  const Dropdown = {
    init() {
      document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-dropdown-toggle]');

        if (trigger) {
          e.stopPropagation();
          const dropdown = trigger.closest('.dropdown');
          const isOpen = dropdown.classList.contains('open');

          // Close all other dropdowns
          this.closeAll();

          // Toggle current
          if (!isOpen) {
            dropdown.classList.add('open');
          }
          return;
        }

        // Click outside closes
        this.closeAll();
      });

      // ESC key closes
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          this.closeAll();
        }
      });
    },

    closeAll() {
      document.querySelectorAll('.dropdown.open').forEach(d => {
        d.classList.remove('open');
      });
    }
  };

  // ─── URL STATE HANDLER ───
  const UrlState = {
    get(param) {
      const url = new URL(window.location.href);
      return url.searchParams.get(param);
    },

    set(param, value) {
      const url = new URL(window.location.href);
      url.searchParams.set(param, value);
      window.history.pushState({}, '', url);
    },

    remove(param) {
      const url = new URL(window.location.href);
      url.searchParams.delete(param);
      window.history.pushState({}, '', url);
    }
  };

  // ─── CHECKBOX TODO ───
  const TodoCheckbox = {
    init() {
      document.addEventListener('click', (e) => {
        const checkbox = e.target.closest('.fo-todo-checkbox');
        if (checkbox) {
          checkbox.classList.toggle('checked');
          const item = checkbox.closest('.fo-todo-item');
          if (item) {
            item.classList.toggle('completed');
          }

          // Update check icon
          if (checkbox.classList.contains('checked')) {
            checkbox.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
          } else {
            checkbox.innerHTML = '';
          }
        }
      });
    }
  };

  // ─── INIT ALL ───
  document.addEventListener('DOMContentLoaded', () => {
    Drawer.init();
    Toast.init();
    Tabs.init();
    Dropdown.init();
    TodoCheckbox.init();

    // Check for state param and show toast if success
    const state = UrlState.get('state');
    if (state === 'success') {
      Toast.success('Succes', 'Action effectuee avec succes.');
    }
  });

  // Expose to global
  window.StudySprint = {
    Drawer,
    Toast,
    Tabs,
    Dropdown,
    UrlState
  };

})();
