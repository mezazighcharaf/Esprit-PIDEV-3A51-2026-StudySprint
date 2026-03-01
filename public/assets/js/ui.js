/* ============================================
   STUDYSPRINT — UI INTERACTIONS
   Vanilla JS: Drawer, Toast, Tabs, Dropdown
   ============================================ */

(function () {
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
    GAP: 4,
    PADDING: 8,

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
            // Position fixed dropdowns (e.g. inside tables) so they stay on screen
            if (trigger.hasAttribute('data-dropdown-fixed')) {
              const menu = dropdown.querySelector('.dropdown-menu');
              if (menu) {
                requestAnimationFrame(() => this.positionFixedMenu(trigger, menu));
              }
            }
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

    /**
     * Position a dropdown menu with position:fixed so it stays on screen.
     * Used for dropdowns inside overflow containers (e.g. tables).
     * Trigger must have data-dropdown-fixed.
     */
    positionFixedMenu(trigger, menu) {
      menu.style.position = 'fixed';
      const rect = trigger.getBoundingClientRect();
      const menuWidth = menu.offsetWidth;
      const menuHeight = menu.offsetHeight;
      const gap = this.GAP;
      const pad = this.PADDING;
      const vw = window.innerWidth;
      const vh = window.innerHeight;

      let left = rect.left;
      if (left + menuWidth > vw - pad) {
        left = Math.max(pad, vw - menuWidth - pad);
      }
      if (left < pad) {
        left = pad;
      }

      let top = rect.bottom + gap;
      if (top + menuHeight > vh - pad) {
        top = rect.top - gap - menuHeight;
      }
      if (top < pad) {
        top = pad;
      }
      if (top + menuHeight > vh - pad) {
        top = vh - menuHeight - pad;
      }

      menu.style.left = left + 'px';
      menu.style.top = top + 'px';
      menu.style.right = 'auto';
      menu.style.bottom = 'auto';
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

  // ─── MODAL ───
  const Modal = {
    init() {
      document.addEventListener('click', (e) => {
        // Open modal
        const openTrigger = e.target.closest('[data-modal-open]');
        if (openTrigger) {
          const modalId = openTrigger.dataset.modalOpen;
          this.open(modalId);
          return;
        }

        // Close modal
        const closeTrigger = e.target.closest('[data-modal-close]');
        if (closeTrigger) {
          const modal = closeTrigger.closest('.modal');
          if (modal) {
            this.close(modal.id);
          }
          return;
        }

        // Click on modal backdrop (outside dialog)
        if (e.target.classList.contains('modal')) {
          this.close(e.target.id);
        }
      });

      // ESC key closes modal
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          const modal = document.querySelector('.modal.open');
          if (modal) {
            this.close(modal.id);
          }
        }
      });
    },

    open(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
      }
    },

    close(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
      }
    },

    closeAll() {
      document.querySelectorAll('.modal.open').forEach(modal => {
        modal.classList.remove('open');
      });
      document.body.style.overflow = '';
    }
  };

  // ─── CONFIRM DIALOG ───
  const ConfirmDialog = {
    callback: null,

    init() {
      document.addEventListener('click', (e) => {
        // Handle kick member button
        const kickBtn = e.target.closest('.kick-member-btn');
        if (kickBtn) {
          const memberName = kickBtn.dataset.memberName;
          const memberId = kickBtn.dataset.memberId;
          const groupId = kickBtn.dataset.groupId;

          // Store reference to the member row for removal
          const memberRow = kickBtn.closest('.member-item');

          this.show(
            `Retirer ${memberName} du groupe?`,
            'Cette action ne peut pas être annulée.',
            () => {
              // Handle member removal
              Toast.success('Succès', `${memberName} a été retiré du groupe.`);
              // Remove the member from the list
              if (memberRow) {
                memberRow.remove();
              }
            }
          );
          return;
        }
      });

      // Handle confirm button
      const confirmBtn = document.getElementById('confirm-action-btn');
      if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
          if (this.callback) {
            this.callback();
          }
          Modal.close('confirm-dialog');
          this.callback = null;
        });
      }
    },

    show(title, message, onConfirm) {
      const dialog = document.getElementById('confirm-dialog');
      const titleEl = dialog.querySelector('.modal-title');
      const messageEl = document.getElementById('confirm-message');

      if (titleEl) titleEl.textContent = title;
      if (messageEl) messageEl.textContent = message;
      this.callback = onConfirm;

      Modal.open('confirm-dialog');
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

  // ─── MEMBER MANAGEMENT ───
  const MemberManagement = {
    init() {
      document.addEventListener('click', (e) => {
        const memberActionBtn = e.target.closest('.member-action-btn');
        if (!memberActionBtn) return;

        e.preventDefault();
        e.stopPropagation();

        // Get the member item container
        const memberItem = memberActionBtn.closest('.member-item');
        if (!memberItem) return;

        const userId = memberItem.dataset.memberUserId;
        const action = memberActionBtn.dataset.action;
        const newRole = memberActionBtn.dataset.newRole;
        const csrfToken = memberActionBtn.dataset.csrf;
        const memberName = memberItem.querySelector('.member-name')?.textContent || 'Unknown';
        const groupId = this.getGroupId(memberItem);
        if (!groupId) return;

        if (action === 'remove') {
          this.handleRemoveMember(userId, memberName, csrfToken, memberItem, groupId);
        } else if (action === 'change-role') {
          this.handleChangeRole(userId, newRole, csrfToken, memberItem, memberName, groupId);
        }

        // Close dropdown
        Dropdown.closeAll();
      });
    },

    handleRemoveMember(userId, memberName, csrfToken, memberItem, groupId) {
      if (!groupId) return;

      // Show confirmation dialog
      ConfirmDialog.show(
        `Retirer ${memberName} du groupe?`,
        'Cette action ne peut pas être annulée.',
        () => {
          // Make API call
          fetch(`/app/groupes/${groupId}/membres/${userId}/remove`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: `_token=${encodeURIComponent(csrfToken)}`
          })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                Toast.success('Succès', `${memberName} a été retiré du groupe.`);

                // Update counters
                const section = memberItem.closest('[data-member-section]');
                if (section) {
                  const sectionId = section.dataset.memberSection;
                  const sectionCounter = section.querySelector(`[data-member-section-count="${sectionId}"]`);
                  if (sectionCounter) {
                    const currentCount = parseInt(sectionCounter.textContent);
                    sectionCounter.textContent = Math.max(0, currentCount - 1);
                    if (currentCount - 1 <= 0) {
                      section.remove();
                    }
                  }
                }

                // Update global counter (prefer counter in same modal/context as memberItem)
                const context = memberItem.closest('[data-group-id]') || memberItem.closest('.group-detail-sidebar');
                const globalCounter = context ? context.querySelector('[data-member-count]') : document.querySelector('[data-member-count]');
                if (globalCounter) {
                  globalCounter.textContent = Math.max(0, parseInt(globalCounter.textContent, 10) - 1);
                }

                // Animate and remove the member from the list
                memberItem.style.transition = 'opacity 0.3s ease-out';
                memberItem.style.opacity = '0';
                setTimeout(() => memberItem.remove(), 300);
              } else {
                Toast.error('Erreur', data.error || 'Erreur lors du retrait du membre');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              Toast.error('Erreur', 'Erreur lors de la communication avec le serveur');
            });
        }
      );
    },

    handleChangeRole(userId, newRole, csrfToken, memberItem, memberName, groupId) {
      if (!groupId) return;

      let roleLabel = '';
      if (newRole === 'admin') roleLabel = 'administrateur';
      else if (newRole === 'moderator') roleLabel = 'modérateur';
      else if (newRole === 'member') roleLabel = 'membre';

      // Make API call
      fetch(`/app/groupes/${groupId}/membres/${userId}/role`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: `role=${encodeURIComponent(newRole)}&_token=${encodeURIComponent(csrfToken)}`
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            Toast.success('Succès', `${memberName} est maintenant ${roleLabel}.`);

            // Reload the page to refresh member organization by role
            setTimeout(() => {
              window.location.reload();
            }, 1500);
          } else {
            Toast.error('Erreur', data.error || 'Erreur lors du changement de rôle');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          Toast.error('Erreur', 'Erreur lors de la communication avec le serveur');
        });
    },

    getGroupId(memberItem) {
      // BO modals: group id on modal container (data-group-id)
      const withContext = memberItem && memberItem.closest('[data-group-id]');
      if (withContext) {
        return withContext.getAttribute('data-group-id');
      }
      // FO group detail: extract from URL
      const match = window.location.pathname.match(/\/app\/groupes\/(\d+)/);
      return match ? match[1] : null;
    }
  };

  // ─── GROUP SEARCH ───
  const GroupSearch = {
    init() {
      const searchInput = document.getElementById('public-group-search');
      if (!searchInput) return;

      const items = document.querySelectorAll('[data-public-group-item]');
      const noResults = document.getElementById('public-groups-no-results');

      searchInput.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase().trim();
        let hasResults = false;

        items.forEach(item => {
          const content = item.dataset.searchContent || '';
          if (content.includes(query)) {
            item.style.display = '';
            hasResults = true;
          } else {
            item.style.display = 'none';
          }
        });

        if (noResults) {
          noResults.style.display = hasResults ? 'none' : 'block';
          const queryDisplay = noResults.querySelector('p strong') || noResults.querySelector('p');
          if (queryDisplay) {
            queryDisplay.innerHTML = query ? `Nous n'avons trouvé aucun groupe public correspondant à <strong>"${e.target.value}"</strong>.` : "Aucun groupe ne correspond à votre recherche.";
          }
        }
      });
    }
  };

  // ─── GROUP MANAGEMENT ───
  const GroupManagement = {
    init() {
      // Handle group creation form
      const createForm = document.getElementById('group-create-form');
      if (createForm) {
        createForm.addEventListener('submit', (e) => {
          const submitBtn = createForm.querySelector('[type="submit"]');
          this.handleFormSubmit(e, createForm, submitBtn, 'create');
        });
      }

      // Handle group edit form
      const editForm = document.getElementById('group-edit-form');
      if (editForm) {
        editForm.addEventListener('submit', (e) => {
          const submitBtn = editForm.querySelector('[type="submit"]');
          this.handleFormSubmit(e, editForm, submitBtn, 'edit');
        });
      }

      // Handle group deletion button
      document.addEventListener('click', (e) => {
        const leaveGroupBtn = e.target.closest('.leave-group-btn');
        if (leaveGroupBtn) {
          e.preventDefault();
          e.stopPropagation();
          const groupId = leaveGroupBtn.dataset.groupId;
          const groupName = leaveGroupBtn.dataset.groupName;
          const action = leaveGroupBtn.dataset.action;
          const csrfToken = leaveGroupBtn.dataset.csrf;
          this.handleDeleteGroup(groupId, groupName, action, csrfToken);
        }

        // Handle cancel invitation button
        const cancelBtn = e.target.closest('.cancel-invitation-btn');
        if (cancelBtn) {
          e.preventDefault();
          e.stopPropagation();
          const email = cancelBtn.dataset.email;
          const form = cancelBtn.closest('.cancel-invitation-form');
          ConfirmDialog.show(
            `Annuler l'invitation ?`,
            `L'invitation envoyée à ${email} sera annulée.`,
            () => { if (form) form.submit(); }
          );
        }
      });
    },

    handleFormSubmit(e, form, submitBtn, action) {
      // Get form data
      const formData = new FormData(form);
      const submitBtnOriginal = submitBtn ? submitBtn.textContent : '';

      // Show loading state
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = action === 'create' ? 'Création en cours...' : 'Enregistrement en cours...';
      }

      // Submit form via AJAX instead of standard form submission
      e.preventDefault();

      const groupName = formData.get('group_create_form[name]') || formData.get('group_edit_form[name]') || 'Groupe';

      fetch(form.action, {
        method: form.method || 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(response => {
          const contentType = response.headers.get('content-type') || '';

          // If successful response (2xx)
          if (response.ok) {
            // Try to parse as JSON first
            if (contentType.includes('application/json')) {
              return response.json().then(data => ({ data, ok: true, isJson: true }));
            } else {
              // If HTML response with 2xx status, it's a successful redirect
              return { ok: true, isJson: false, redirectUrl: response.url };
            }
          } else {
            // Error response
            if (contentType.includes('application/json')) {
              return response.json().then(data => ({ data, ok: false, isJson: true }));
            } else {
              throw new Error('Form submission failed');
            }
          }
        })
        .then(result => {
          if (result.ok) {
            // Success case
            const message = action === 'create'
              ? `${groupName} a été créé avec succès.`
              : `${groupName} a été mis à jour.`;
            Toast.success('Succès', message);

            // Redirect after showing toast
            setTimeout(() => {
              if (result.isJson && result.data.redirect) {
                window.location.href = result.data.redirect;
              } else if (!result.isJson && result.redirectUrl) {
                window.location.href = result.redirectUrl;
              } else {
                window.location.href = '/app/groupes';
              }
            }, 1500);
          } else {
            // Error case
            const errorMsg = result.data?.error || (action === 'create' ? 'Erreur lors de la création du groupe' : 'Erreur lors de la mise à jour du groupe');
            Toast.error('Erreur', errorMsg);

            if (submitBtn) {
              submitBtn.disabled = false;
              submitBtn.textContent = submitBtnOriginal;
            }
          }
        })
        .catch(error => {
          console.error('Error:', error);
          Toast.error('Erreur', 'Erreur lors de la communication avec le serveur');

          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = submitBtnOriginal;
          }
        });
    },

    handleDeleteGroup(groupId, groupName, action, csrfToken) {
      if (!groupId) return;

      // Show confirmation dialog - same styling as member removal
      ConfirmDialog.show(
        action === 'delete' ? `Supprimer ${groupName}?` : `Quitter ${groupName}?`,
        'Cette action ne peut pas être annulée.',
        () => {
          // Make API call to delete/leave group
          const token = csrfToken ||
            document.querySelector('[name="_token"]')?.value ||
            document.querySelector('input[name="_token"]')?.value;

          fetch(`/app/groupes/${groupId}/${action === 'delete' ? 'supprimer' : 'leave'}`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: `_token=${encodeURIComponent(token || '')}`
          })
            .then(response => {
              const contentType = response.headers.get('content-type') || '';
              if (response.ok) {
                if (contentType.includes('application/json')) {
                  return response.json().then(data => ({ data, ok: true, isJson: true }));
                } else {
                  return { ok: true, isJson: false };
                }
              } else {
                if (contentType.includes('application/json')) {
                  return response.json().then(data => ({ data, ok: false, isJson: true }));
                } else {
                  throw new Error('Request failed');
                }
              }
            })
            .then(result => {
              if (result.ok) {
                const message = action === 'delete'
                  ? `${groupName} a été supprimé avec succès.`
                  : `Vous avez quitté ${groupName} avec succès.`;
                Toast.success('Succès', message);

                // Redirect after a short delay
                setTimeout(() => {
                  const isBO = window.location.pathname.startsWith('/admin');
                  window.location.href = isBO ? '/admin/encadrement' : '/app/groupes';
                }, 1500);
              } else {
                Toast.error('Erreur', result.data?.error || `Erreur lors du ${action} du groupe`);
              }
            })
            .catch(error => {
              console.error('Error:', error);
              Toast.error('Erreur', 'Erreur lors de la communication avec le serveur');
            });
        }
      );
    }
  };

  // ─── POST MANAGEMENT ───
  const PostManagement = {
    groupId: null,

    init(groupId) {
      if (!groupId) return;
      this.groupId = groupId;
      this.bindPostFormEvents();
      this.bindPostInteractions();
      this.initSorting();
    },

    bindPostFormEvents() {
      const container = document.querySelector('[data-post-form]');
      if (!container) return;

      const trigger = container.querySelector('[data-post-form-trigger]');
      const typesWrapper = container.querySelector('[data-post-types]');
      const forms = container.querySelectorAll('[data-post-input-form]');
      const typeBtns = container.querySelectorAll('.forum-post-type-btn');
      const cancelBtns = container.querySelectorAll('[data-post-cancel]');
      const fileTrigger = container.querySelector('[data-file-trigger]');
      const fileInput = container.querySelector('#forum-file-input');
      const fileList = container.querySelector('[data-file-list]');

      // Show forms when clicking trigger
      if (trigger) {
        trigger.addEventListener('click', () => {
          trigger.parentElement.style.display = 'none';
          if (typesWrapper) typesWrapper.style.display = 'flex';
          const textForm = container.querySelector('[data-post-input-form="text"]');
          if (textForm) textForm.style.display = 'block';
        });
      }

      // Handle type switching
      typeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
          const type = btn.dataset.type;
          typeBtns.forEach(b => b.classList.remove('active'));
          btn.classList.add('active');

          forms.forEach(f => {
            f.style.display = f.dataset.postType === type ? 'block' : 'none';
          });
        });
      });

      // Handle cancel
      cancelBtns.forEach(btn => {
        btn.addEventListener('click', () => {
          this.hidePostForm();
        });
      });

      // File upload trigger
      if (fileTrigger && fileInput) {
        fileTrigger.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => {
          if (fileList) {
            fileList.innerHTML = '';
            Array.from(fileInput.files).forEach(file => {
              const div = document.createElement('div');
              div.className = 'fo-file-item';
              div.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                  <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/>
                </svg>
                <span>${file.name}</span>
              `;
              fileList.appendChild(div);
            });
          }
        });
      }

      // Submit handling
      forms.forEach(form => {
        form.addEventListener('submit', (e) => {
          e.preventDefault();
          this.handlePostCreate(form);
        });
      });
    },

    async handlePostCreate(form) {
      const postType = form.dataset.postType || 'text';
      const formData = new FormData(form);
      formData.set('post_type', postType); // Ensure type is set from dataset
      formData.set('_token', this.getCsrfToken('create-post-' + this.groupId));

      const submitBtn = form.querySelector('[type="submit"]');
      const originalText = submitBtn.textContent;
      submitBtn.disabled = true;
      submitBtn.textContent = 'Publication...';

      try {
        const response = await fetch(`/app/groupes/${this.groupId}/posts/create`, {
          method: 'POST',
          body: formData,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const data = await response.json();

        if (data.success) {
          Toast.success('Succès', data.message);

          // Register new CSRF tokens
          if (data.csrf_tokens) {
            this.registerCsrfTokens(data.post.id, data.csrf_tokens);
          }

          this.prependPost(data.post);

          // Show bot auto-comment if present
          if (data.botReply) {
            setTimeout(() => {
              Toast.success(data.botReply.botName || 'StudyBot', 'A commenté votre publication');
              // Reload comments for the post to display the bot comment
              this.loadComments(data.post.id);
              // Update comment counter
              const countBadge = document.querySelector(`[data-comments-count="${data.post.id}"]`);
              if (countBadge) countBadge.textContent = parseInt(countBadge.textContent || '0') + 1;
            }, 500);
          }

          form.reset();

          // Reset file input if exists
          const fileList = form.querySelector('[data-file-list]');
          if (fileList) fileList.innerHTML = '';

          this.hidePostForm();
        } else {
          Toast.error('Erreur', data.error || 'Erreur lors de la création du post');
        }
      } catch (error) {
        console.error('Error creating post:', error);
        Toast.error('Erreur', 'Erreur de connexion');
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
      }
    },

    initSorting() {
      const toolbar = document.querySelector('.fo-posts-toolbar');
      if (!toolbar) return;

      const buttons = toolbar.querySelectorAll('.fo-sort-btn');
      buttons.forEach(btn => {
        btn.addEventListener('click', async (e) => {
          e.preventDefault();

          buttons.forEach(b => b.classList.remove('active'));
          btn.classList.add('active');

          const sortType = btn.dataset.sort;
          await this.loadSortedPosts(sortType);
        });
      });
    },

    async loadSortedPosts(sortType) {
      const container = document.getElementById('posts-container');
      if (!container) return;

      container.style.opacity = '0.5';

      try {
        const response = await fetch(`/app/groupes/${this.groupId}/posts?sort=${sortType}`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (response.ok) {
          const html = await response.text();
          container.innerHTML = html;
        } else {
          Toast.error('Erreur', 'Impossible de charger les posts triés');
        }
      } catch (error) {
        console.error('Error loading sorted posts:', error);
        Toast.error('Erreur', 'Erreur de connexion');
      } finally {
        container.style.opacity = '1';
      }
    },

    prependPost(post) {
      const postsContainer = document.querySelector('.fo-posts-list');
      if (!postsContainer) return;

      const postHtml = this.createPostHtml(post);
      postsContainer.insertAdjacentHTML('afterbegin', postHtml);
    },

    createPostHtml(post) {
      // Helper to determine role badge
      let roleBadge = '';
      if (post.author.role === 'admin') {
        roleBadge = '<span class="badge-role-admin">Admin</span>';
      } else if (post.author.role === 'moderator') {
        roleBadge = '<span class="badge-role-moderator">Mod</span>';
      } else {
        roleBadge = '<span class="badge-role-member">Membre</span>';
      }

      return `
            <article class="fo-post-card" data-post-id="${post.id}">
                <div class="fo-post-header">
                    <div class="fo-post-author">
                        <div class="avatar avatar-sm avatar-initials">${post.author.initials}</div>
                        <div class="fo-post-meta">
                            <div class="fo-post-author-name">
                                ${post.author.name}
                                ${roleBadge}
                            </div>
                            <div class="fo-post-date">${post.timeAgo}</div>
                        </div>
                    </div>
                    ${post.canDelete ? `
                    <button class="btn btn-ghost btn-icon btn-xs" data-post-delete="${post.id}" title="Supprimer">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>` : ''}
                </div>
                
                <div class="fo-post-body-content">
                    ${post.title ? `<h3 class="fo-post-title">${this.escapeHtml(post.title)}</h3>` : ''}
                    <p class="fo-post-text">${this.escapeHtml(post.body)}</p>
                    ${post.attachmentUrl ? (post.type === 'file' ? `
                    <a href="${post.attachmentUrl}" target="_blank" class="fo-post-attachment" download>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/>
                            <polyline points="13 2 13 9 20 9"/>
                        </svg>
                        ${this.escapeHtml(post.attachmentName || post.attachmentUrl)}
                    </a>` : `
                    <a href="${post.attachmentUrl}" target="_blank" class="fo-post-attachment">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                        </svg>
                        ${this.escapeHtml(post.attachmentUrl)}
                    </a>`) : ''}
                </div>

                <div class="fo-post-actions">
                    <button class="fo-post-action-btn ${post.stats.userLiked ? 'liked' : ''}" data-post-like="${post.id}" data-liked="${post.stats.userLiked}">
                        <svg viewBox="0 0 24 24" fill="${post.stats.userLiked ? 'var(--color-error)' : 'none'}" stroke="${post.stats.userLiked ? 'var(--color-error)' : 'currentColor'}" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                        <span data-likes-count="${post.id}">${post.stats.likesCount}</span>
                    </button>
                    
                    <button class="fo-post-action-btn" data-comments-toggle="${post.id}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span data-comments-count="${post.id}">${post.stats.commentsCount}</span>
                    </button>

                    <div class="fo-rating" data-post-rating="${post.id}">
                        ${this.createRatingStars(post.id, post.stats.averageRating, post.stats.userRating)}
                        <span class="text-xs text-muted ml-1" data-avg-rating="${post.id}">${post.stats.averageRating.toFixed(1)}</span>
                    </div>
                </div>

                <div class="fo-comments-section" data-comments-section="${post.id}" style="display: none;">
                    <div class="fo-comment-list" data-comments-list="${post.id}"></div>
                    <form class="fo-comment-form" data-comment-form="${post.id}">
                        <textarea class="form-input" name="body" placeholder="Ajouter un commentaire..." rows="1" required></textarea>
                        <button type="submit" class="btn btn-primary btn-sm">Envoyer</button>
                    </form>
                </div>
            </article>
      `;
    },

    createRatingStars(postId, avgRating, userRating) {
      let starsHtml = '';
      for (let i = 1; i <= 5; i++) {
        const filled = userRating >= i;
        starsHtml += `
          <svg class="fo-rating-star ${filled ? 'filled' : ''}" data-rate-post="${postId}" data-rating="${i}" 
               viewBox="0 0 24 24" fill="${filled ? 'var(--color-warning)' : 'none'}" stroke="var(--color-warning)" stroke-width="2" width="16" height="16">
              <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
          </svg>`;
      }
      return starsHtml;
    },

    bindPostInteractions() {
      // Use event delegation for all post-related clicks
      document.addEventListener('click', (e) => {
        // Like button
        const likeBtn = e.target.closest('[data-post-like]');
        if (likeBtn) {
          e.preventDefault();
          this.handleLikeToggle(likeBtn.dataset.postLike);
          return;
        }

        // Rating stars
        const star = e.target.closest('[data-rate-post]');
        if (star) {
          e.preventDefault();
          e.stopPropagation();
          this.handleRating(star.dataset.ratePost, parseInt(star.dataset.rating));
          return;
        }

        // Comments toggle
        const toggleBtn = e.target.closest('[data-comments-toggle]');
        if (toggleBtn) {
          e.preventDefault();
          this.toggleComments(toggleBtn.dataset.commentsToggle);
          return;
        }

        // Delete Post
        const deleteBtn = e.target.closest('[data-post-delete]');
        if (deleteBtn) {
          e.preventDefault();
          this.handlePostDelete(deleteBtn.dataset.postDelete);
          return;
        }

        // Reply to comment
        const replyBtn = e.target.closest('[data-reply-to]');
        if (replyBtn) {
          e.preventDefault();
          this.handleReplyClick(replyBtn);
          return;
        }

        // Delete Comment
        const deleteCommentBtn = e.target.closest('[data-delete-comment]');
        if (deleteCommentBtn) {
          e.preventDefault();
          const commentId = deleteCommentBtn.dataset.deleteComment;
          const deleteToken = deleteCommentBtn.dataset.deleteToken;
          const postCard = deleteCommentBtn.closest('.fo-post-card');
          const postId = postCard ? postCard.dataset.postId : null;
          if (postId) this.handleCommentDelete(commentId, postId, deleteToken);
          return;
        }

        // Bot feedback
        const feedbackBtn = e.target.closest('[data-bot-feedback]');
        if (feedbackBtn) {
          e.preventDefault();
          this.handleBotFeedback(feedbackBtn);
          return;
        }

        // Ask Bot button
        const askBotBtn = e.target.closest('[data-ask-bot]');
        if (askBotBtn) {
          e.preventDefault();
          this.handleAskBot(askBotBtn);
          return;
        }
      });

      // Delegate comment form submissions
      document.addEventListener('submit', (e) => {
        const commentForm = e.target.closest('[data-comment-form]');
        if (commentForm) {
          e.preventDefault();
          this.handleCommentCreate(commentForm.dataset.commentForm, commentForm);
        }
      });
    },

    handleReplyClick(replyBtn) {
      const commentId = replyBtn.dataset.replyTo;
      const postCard = replyBtn.closest('.fo-post-card');
      if (!postCard) return;

      const form = postCard.querySelector('.fo-comment-form');
      if (!form) return;

      form.dataset.parentId = commentId;

      // Find the comment being replied to for context
      const commentEl = replyBtn.closest('[data-comment-id]');
      const isBot = commentEl && commentEl.classList.contains('fo-comment-bot');
      const authorEl = commentEl ? commentEl.querySelector('.fo-comment-author') : null;
      const authorName = authorEl ? authorEl.textContent.trim() : '';

      // Show reply indicator above the textarea
      let indicator = form.querySelector('.fo-reply-indicator');
      if (!indicator) {
        indicator = document.createElement('div');
        indicator.className = 'fo-reply-indicator';
        indicator.style.cssText = 'display:flex; align-items:center; gap:6px; padding:6px 10px; margin-bottom:6px; background:' + (isBot ? 'rgba(99,102,241,0.06)' : 'var(--color-gray-50, #f9fafb)') + '; border-left:3px solid ' + (isBot ? '#6366F1' : 'var(--color-gray-300)') + '; border-radius:0 6px 6px 0; font-size:12px; color:var(--color-gray-600); animation: slideDown 0.2s ease;';
        form.insertBefore(indicator, form.firstChild);
      }
      indicator.innerHTML = `
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
        <span>Répondre à <strong style="${isBot ? 'color:#6366F1' : ''}">${this.escapeHtml(authorName)}</strong></span>
        <button type="button" class="fo-reply-cancel" style="margin-left:auto; background:none; border:none; cursor:pointer; color:var(--color-gray-400); padding:0; font-size:14px; line-height:1;">✕</button>
      `;
      indicator.style.display = 'flex';
      indicator.style.background = isBot ? 'rgba(99,102,241,0.06)' : 'var(--color-gray-50, #f9fafb)';
      indicator.style.borderLeftColor = isBot ? '#6366F1' : 'var(--color-gray-300)';

      // Cancel reply
      indicator.querySelector('.fo-reply-cancel').addEventListener('click', () => {
        delete form.dataset.parentId;
        indicator.style.display = 'none';
        textarea.placeholder = 'Ajouter un commentaire...';
      });

      const textarea = form.querySelector('textarea');
      textarea.placeholder = `Répondre à ${authorName}...`;
      textarea.focus();
    },

    async handleLikeToggle(postId) {
      const formData = new FormData();
      formData.append('_token', this.getCsrfToken('like-post-' + postId));

      try {
        const response = await fetch(`/app/posts/${postId}/like`, {
          method: 'POST',
          body: formData,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();

        if (data.success) {
          const btn = document.querySelector(`[data-post-like="${postId}"]`);
          if (btn) {
            btn.dataset.liked = data.liked;
            btn.classList.toggle('liked', data.liked);
            const svg = btn.querySelector('svg');
            svg.setAttribute('fill', data.liked ? 'var(--color-error)' : 'none');
            svg.setAttribute('stroke', data.liked ? 'var(--color-error)' : 'currentColor');
            const count = btn.querySelector('[data-likes-count]');
            if (count) count.textContent = data.likesCount;
          }
        } else {
          Toast.error('Erreur', data.error);
        }
      } catch (error) {
        Toast.error('Erreur', 'Erreur de connexion');
      }
    },

    async handleRating(postId, rating) {
      const formData = new FormData();
      formData.append('_token', this.getCsrfToken('rate-post-' + postId));
      formData.append('rating', rating);

      try {
        const response = await fetch(`/app/posts/${postId}/rate`, {
          method: 'POST',
          body: formData,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();

        if (data.success) {
          const ratingContainer = document.querySelector(`[data-post-rating="${postId}"]`);
          if (ratingContainer) {
            const stars = ratingContainer.querySelectorAll('.fo-rating-star');
            stars.forEach((star, idx) => {
              const filled = (idx + 1) <= data.userRating;
              star.classList.toggle('filled', filled);
              star.setAttribute('fill', filled ? 'var(--color-warning)' : 'none');
            });
            const avg = ratingContainer.querySelector('[data-avg-rating]');
            if (avg) avg.textContent = data.averageRating.toFixed(1);
          }
          Toast.success('Succès', 'Note enregistrée');
        } else {
          Toast.error('Erreur', data.error);
        }
      } catch (error) {
        Toast.error('Erreur', 'Erreur de connexion');
      }
    },

    async toggleComments(postId) {
      const section = document.querySelector(`[data-comments-section="${postId}"]`);
      if (!section) return;

      if (section.style.display === 'none') {
        section.style.display = 'block';
        await this.loadComments(postId);
      } else {
        section.style.display = 'none';
      }
    },

    async loadComments(postId) {
      try {
        const response = await fetch(`/app/posts/${postId}/comments`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();
        if (data.success) {
          this.renderComments(postId, data.comments);
        }
      } catch (e) { console.error(e); }
    },

    renderComments(postId, comments) {
      const list = document.querySelector(`[data-comments-list="${postId}"]`);
      if (!list) return;

      // Build a map of parentId -> children for recursive rendering
      const childrenMap = {};
      comments.forEach(c => {
        const pid = c.parentId || 0;
        if (!childrenMap[pid]) childrenMap[pid] = [];
        childrenMap[pid].push(c);
      });

      const renderThread = (parentId, depth) => {
        const children = childrenMap[parentId] || [];
        if (children.length === 0) return '';
        let out = depth > 0 ? '<div class="fo-comment-reply-list">' : '';
        children.forEach(c => {
          out += this.createCommentHtml(c, depth > 0);
          // Recurse into sub-replies (limit visual depth to 3)
          out += renderThread(c.id, Math.min(depth + 1, 3));
        });
        out += depth > 0 ? '</div>' : '';
        return out;
      };

      let html = '<div class="fo-comments-wrapper">';
      html += renderThread(0, 0);
      html += '</div>';
      list.innerHTML = html;
      // Logic for binding reply buttons is handled by delegation in bindCommentActions
    },

    createCommentHtml(comment, isReply = false) {
      // Bot comment styling
      if (comment.isBot) {
        // Build reply-to reference if this bot comment is a reply
        let botReplyRef = '';
        if (comment.parentId && comment.parentAuthor) {
          botReplyRef = `<div class="fo-reply-ref" style="font-size: 11px; color: var(--color-gray-500); margin-bottom: 6px; padding: 4px 8px; background: rgba(99,102,241,0.04); border-left: 2px solid #6366F1; border-radius: 0 4px 4px 0;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11" style="vertical-align: -1px; margin-right: 2px;"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
            En réponse à <strong>${this.escapeHtml(comment.parentAuthor)}</strong>
          </div>`;
        }
        return `
            <div class="fo-comment-item fo-comment-bot" data-comment-id="${comment.id}" style="background: linear-gradient(135deg, rgba(99,102,241,0.06) 0%, rgba(139,92,246,0.06) 100%); border: 1px solid rgba(99,102,241,0.15); border-radius: 12px; padding: 12px; margin: 6px 0;">
                <div class="fo-comment-avatar" style="background: linear-gradient(135deg, #6366F1, #8B5CF6); color: white; font-size: 11px; font-weight: 700;">AI</div>
                <div class="fo-comment-content">
                    <div class="fo-comment-header">
                        <span class="fo-comment-author" style="color: #6366F1; font-weight: 600;">${comment.botName || 'StudyBot'}</span>
                        <span style="background: linear-gradient(135deg, #6366F1, #8B5CF6); color: white; font-size: 10px; padding: 1px 6px; border-radius: 10px; font-weight: 600; margin-left: 4px;">IA</span>
                        <span class="fo-comment-date">${comment.timeAgo}</span>
                    </div>
                    ${botReplyRef}
                    <div class="fo-comment-text" data-comment-content="${comment.id}" style="white-space: pre-wrap;">${this.escapeHtml(comment.body)}</div>

                    <div class="fo-comment-translation" data-comment-translation-result="${comment.id}" style="display: none; margin-top: 6px; padding: 8px 10px; background: rgba(99,102,241,0.06); border-left: 3px solid #6366F1; border-radius: 0 6px 6px 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                            <span style="font-size: 10px; color: #6366F1; font-weight: 600;">🌐 Traduction</span>
                            <button type="button" data-comment-translation-close="${comment.id}" style="background: none; border: none; cursor: pointer; color: var(--color-gray-400, #9CA3AF); font-size: 12px; padding: 0; line-height: 1;">✕</button>
                        </div>
                        <p data-comment-translation-text="${comment.id}" style="margin: 0; font-size: 0.85rem; color: var(--color-gray-800, #1e293b);"></p>
                    </div>

                    <div class="fo-comment-actions" style="margin-top: 6px;">
                        <button class="fo-comment-action" data-reply-to="${comment.id}" style="color: #6366F1;">↩ Répondre</button>
                        ${comment.canDelete ? `<button class="fo-comment-action delete" data-delete-comment="${comment.id}" data-delete-token="${comment.deleteToken || ''}">Supprimer</button>` : ''}
                        <div class="fo-comment-translate-wrapper" style="position: relative; display: inline-block; margin-left: 4px;">
                            <button type="button" class="fo-comment-action" data-translate-comment-toggle="${comment.id}" title="Traduire" style="color: #6366F1;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" style="vertical-align: middle; margin-right: 2px;">
                                    <path d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
                                </svg>
                                Traduire
                            </button>
                            <div class="fo-translate-dropdown" data-translate-comment-dropdown="${comment.id}" style="display: none; position: absolute; left: 0; bottom: 100%; margin-bottom: 4px; background: var(--color-white, #fff); border: 1px solid var(--color-gray-200, #e5e7eb); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 8px; z-index: 20; min-width: 140px;">
                                <div style="font-size: 11px; color: var(--color-gray-500, #6B7280); font-weight: 600; padding: 4px 8px; margin-bottom: 4px;">Traduire en :</div>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="fr" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-fr" style="flex-shrink: 0;"></span> Français</button>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="en" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-gb" style="flex-shrink: 0;"></span> English</button>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="es" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-es" style="flex-shrink: 0;"></span> Español</button>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="de" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-de" style="flex-shrink: 0;"></span> Deutsch</button>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="ar" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-sa" style="flex-shrink: 0;"></span> العربية</button>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="it" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-it" style="flex-shrink: 0;"></span> Italiano</button>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="pt" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-pt" style="flex-shrink: 0;"></span> Português</button>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="tr" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-tr" style="flex-shrink: 0;"></span> Türkçe</button>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="zh" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-cn" style="flex-shrink: 0;"></span> 中文</button>
                            </div>
                        </div>
                        ${comment.interactionId ? `
                        <span style="color: var(--color-gray-300);">·</span>
                        <span style="font-size: 11px; color: var(--color-gray-500);">Utile ?</span>
                        <button class="fo-bot-feedback-btn" data-bot-feedback="${comment.interactionId}" data-feedback="helpful" style="background: none; border: 1px solid #d1d5db; border-radius: 6px; padding: 2px 8px; cursor: pointer; font-size: 11px; color: #6b7280; display: flex; align-items: center; gap: 3px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>
                            Oui
                        </button>
                        <button class="fo-bot-feedback-btn" data-bot-feedback="${comment.interactionId}" data-feedback="not-helpful" style="background: none; border: 1px solid #d1d5db; border-radius: 6px; padding: 2px 8px; cursor: pointer; font-size: 11px; color: #6b7280; display: flex; align-items: center; gap: 3px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"/></svg>
                            Non
                        </button>` : ''}
                    </div>
                </div>
            </div>
        `;
      }

      return `
            <div class="fo-comment-item" data-comment-id="${comment.id}">
                <div class="fo-comment-avatar">${comment.author.initials}</div>
                <div class="fo-comment-content">
                    <div class="fo-comment-header">
                        <span class="fo-comment-author">${comment.author.name}</span>
                        <span class="fo-comment-date">${comment.timeAgo}</span>
                    </div>
                    <div class="fo-comment-text" data-comment-content="${comment.id}">${this.escapeHtml(comment.body)}</div>

                    <div class="fo-comment-translation" data-comment-translation-result="${comment.id}" style="display: none; margin-top: 6px; padding: 8px 10px; background: var(--color-primary-50, #EFF6FF); border-left: 3px solid var(--color-primary, #3B82F6); border-radius: 0 6px 6px 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                            <span style="font-size: 10px; color: var(--color-primary, #3B82F6); font-weight: 600;">🌐 Traduction</span>
                            <button type="button" data-comment-translation-close="${comment.id}" style="background: none; border: none; cursor: pointer; color: var(--color-gray-400, #9CA3AF); font-size: 12px; padding: 0; line-height: 1;">✕</button>
                        </div>
                        <p data-comment-translation-text="${comment.id}" style="margin: 0; font-size: 0.85rem; color: var(--color-gray-800, #1e293b);"></p>
                    </div>

                    ${comment.parentId && comment.parentAuthor ? `
                    <div class="fo-reply-ref" style="font-size: 11px; color: var(--color-gray-500); margin-bottom: 4px; padding: 3px 8px; background: var(--color-gray-50, #f9fafb); border-left: 2px solid var(--color-gray-300); border-radius: 0 4px 4px 0;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11" style="vertical-align: -1px; margin-right: 2px;"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
                        En réponse à <strong>${this.escapeHtml(comment.parentAuthor)}</strong>
                    </div>` : ''}

                    <div class="fo-comment-actions">
                        <button class="fo-comment-action" data-reply-to="${comment.id}">Répondre</button>
                        ${comment.canDelete ? `<button class="fo-comment-action delete" data-delete-comment="${comment.id}" data-delete-token="${comment.deleteToken || ''}">Supprimer</button>` : ''}
                        <div class="fo-comment-translate-wrapper" style="position: relative; display: inline-block; margin-left: 4px;">
                            <button type="button" class="fo-comment-action" data-translate-comment-toggle="${comment.id}" title="Traduire">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" style="vertical-align: middle; margin-right: 2px;">
                                    <path d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
                                </svg>
                                Traduire
                            </button>
                            <div class="fo-translate-dropdown" data-translate-comment-dropdown="${comment.id}" style="display: none; position: absolute; left: 0; bottom: 100%; margin-bottom: 4px; background: var(--color-white, #fff); border: 1px solid var(--color-gray-200, #e5e7eb); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 8px; z-index: 20; min-width: 140px;">
                                <div style="font-size: 11px; color: var(--color-gray-500, #6B7280); font-weight: 600; padding: 4px 8px; margin-bottom: 4px;">Traduire en :</div>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="fr" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-fr" style="flex-shrink: 0;"></span> Français</button>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="en" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-gb" style="flex-shrink: 0;"></span> English</button>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="es" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-es" style="flex-shrink: 0;"></span> Español</button>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="de" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-de" style="flex-shrink: 0;"></span> Deutsch</button>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="ar" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-sa" style="flex-shrink: 0;"></span> العربية</button>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="it" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-it" style="flex-shrink: 0;"></span> Italiano</button>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="pt" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-pt" style="flex-shrink: 0;"></span> Português</button>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="tr" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-tr" style="flex-shrink: 0;"></span> Türkçe</button>
                                <button type="button" class="fo-translate-lang-btn" data-translate-comment="${comment.id}" data-lang="zh" style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 5px 8px; border: none; background: none; cursor: pointer; border-radius: 4px; font-size: 12px; color: var(--color-gray-700, #374151);"><span class="fi fi-cn" style="flex-shrink: 0;"></span> 中文</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    async handleCommentCreate(postId, form) {
      const textarea = form.querySelector('textarea');
      const body = textarea.value.trim();
      if (!body) return;

      const formData = new FormData();
      formData.append('_token', this.getCsrfToken('comment-post-' + postId));
      formData.append('body', body);
      if (form.dataset.parentId) {
        formData.append('parent_id', form.dataset.parentId);
      }

      const btn = form.querySelector('button');
      btn.disabled = true;

      try {
        const response = await fetch(`/app/posts/${postId}/comments/create`, {
          method: 'POST',
          body: formData,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();

        if (data.success) {
          textarea.value = '';
          delete form.dataset.parentId;
          textarea.placeholder = 'Ajouter un commentaire...';
          // Hide reply indicator
          const indicator = form.querySelector('.fo-reply-indicator');
          if (indicator) indicator.style.display = 'none';
          await this.loadComments(postId);

          // Update counter
          const countBadge = document.querySelector(`[data-comments-count="${postId}"]`);
          if (countBadge) {
            let newCount = parseInt(countBadge.textContent) + 1;
            // If bot replied, add one more
            if (data.botReply) {
              newCount++;
            }
            countBadge.textContent = newCount;
          }

          // Show bot reply notification
          if (data.botReply) {
            Toast.success(data.botReply.botName || 'StudyBot', 'Le chatbot a répondu à votre commentaire');
          }
        } else {
          Toast.error('Erreur', data.error);
        }
      } catch (e) {
        Toast.error('Erreur', 'Erreur de connexion');
      } finally {
        btn.disabled = false;
      }
    },

    async handleCommentDelete(commentId, postId, deleteToken) {
      ConfirmDialog.show('Supprimer le commentaire ?', 'Cette action est irréversible.', async () => {
        try {
          const formData = new FormData();
          // Use the token passed from the button data attribute
          formData.append('_token', deleteToken || this.getCsrfToken('delete-comment-' + commentId));

          const response = await fetch(`/app/comments/${commentId}/delete`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          });
          const data = await response.json();
          if (data.success) {
            Toast.success('Succès', 'Commentaire supprimé');
            await this.loadComments(postId);
            const countBadge = document.querySelector(`[data-comments-count="${postId}"]`);
            if (countBadge) countBadge.textContent = Math.max(0, parseInt(countBadge.textContent) - 1);
          } else {
            Toast.error('Erreur', data.error);
          }
        } catch (e) {
          Toast.error('Erreur', 'Erreur de connexion');
        }
      });
    },

    async handleBotFeedback(btn) {
      const interactionId = btn.dataset.botFeedback;
      const feedback = btn.dataset.feedback;

      try {
        const response = await fetch(`/app/api/chatbot/interactions/${interactionId}/feedback`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ feedback }),
        });
        const data = await response.json();
        if (data.success) {
          // Highlight selected button
          const feedbackContainer = btn.closest('.fo-bot-feedback');
          if (feedbackContainer) {
            feedbackContainer.querySelectorAll('.fo-bot-feedback-btn').forEach(b => {
              b.disabled = true;
              b.style.opacity = '0.5';
            });
            btn.style.opacity = '1';
            btn.style.borderColor = feedback === 'helpful' ? '#22c55e' : '#ef4444';
            btn.style.color = feedback === 'helpful' ? '#22c55e' : '#ef4444';
          }
          Toast.success('Merci', 'Votre feedback a été enregistré');
        }
      } catch (e) {
        Toast.error('Erreur', 'Impossible d\'envoyer le feedback');
      }
    },

    async handleAskBot(btn) {
      const postId = btn.dataset.askBot;
      const groupId = this.groupId;
      const postCard = btn.closest('.fo-post-card');
      if (!postCard) return;

      const input = postCard.querySelector('[data-bot-question-input]');
      if (!input) return;

      const question = input.value.trim();
      if (!question) {
        Toast.error('Erreur', 'Veuillez saisir une question');
        return;
      }

      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-sm"></span> Réflexion...';

      try {
        const response = await fetch(`/app/api/chatbot/groups/${groupId}/posts/${postId}/ask`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ question }),
        });
        const data = await response.json();

        if (data.success) {
          input.value = '';
          await this.loadComments(postId);

          // Ensure comments section is visible
          const section = postCard.querySelector(`[data-comments-section="${postId}"]`);
          if (section) section.style.display = 'block';

          // Update counter
          const countBadge = document.querySelector(`[data-comments-count="${postId}"]`);
          if (countBadge) countBadge.textContent = parseInt(countBadge.textContent) + 1;

          Toast.success(data.comment.botName || 'StudyBot', 'A répondu à votre question');
        } else {
          Toast.error('Erreur', data.error);
        }
      } catch (e) {
        Toast.error('Erreur', 'Impossible de contacter le chatbot');
      } finally {
        btn.disabled = false;
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4z"/></svg> Demander`;
      }
    },

    async handlePostDelete(postId) {
      ConfirmDialog.show('Supprimer ce post ?', 'Cette action est irréversible.', async () => {
        const formData = new FormData();
        formData.append('_token', this.getCsrfToken('delete-post-' + postId));

        try {
          const response = await fetch(`/app/groupes/${this.groupId}/posts/${postId}/delete`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          });
          const data = await response.json();
          if (data.success) {
            Toast.success('Succès', 'Post supprimé');
            const el = document.querySelector(`[data-post-id="${postId}"]`);
            if (el) el.remove();
          } else {
            Toast.error('Erreur', data.error);
          }
        } catch (e) {
          Toast.error('Erreur', 'Erreur de connexion');
        }
      });
    },

    hidePostForm() {
      // Hide all input forms
      document.querySelectorAll('[data-post-input-form]').forEach(f => f.style.display = 'none');

      // Hide types selector
      const types = document.querySelector('[data-post-types]');
      if (types) types.style.display = 'none';

      // Restore the trigger header (placeholder input)
      const trigger = document.querySelector('[data-post-form-trigger]');
      if (trigger && trigger.parentElement) {
        trigger.parentElement.style.display = 'flex';
      }
    },

    getCsrfToken(id) {
      const el = document.querySelector(`meta[name="csrf-token-${id}"]`);
      return el ? el.content : '';
    },

    registerCsrfTokens(postId, tokens) {
      // Create meta tags for the new tokens so getCsrfToken can find them
      const types = ['like', 'rate', 'delete', 'comment'];
      types.forEach(type => {
        if (tokens[type]) {
          const name = `${type}-post-${postId}`;
          let meta = document.querySelector(`meta[name="csrf-token-${name}"]`);
          if (!meta) {
            meta = document.createElement('meta');
            meta.name = `csrf-token-${name}`;
            document.head.appendChild(meta);
          }
          // Set the token content
          meta.content = tokens[type];
        }
      });
    },

    escapeHtml(unsafe) {
      if (!unsafe) return '';
      return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }
  };

  const GroupDashboard = {
    init() {
      this.searchInput = document.getElementById('group-search-input');
      this.sortSelect = document.getElementById('group-sort-select');
      this.roleFilter = document.getElementById('group-filter-role');
      this.container = document.getElementById('groups-container');

      if (!this.container) return;

      this.cards = Array.from(this.container.querySelectorAll('[data-group-card]'));

      this.bindEvents();
    },

    bindEvents() {
      if (this.searchInput) {
        this.searchInput.addEventListener('input', () => this.applyFilters());
      }
      if (this.sortSelect) {
        this.sortSelect.addEventListener('change', () => this.applySort());
      }
      if (this.roleFilter) {
        this.roleFilter.addEventListener('change', () => this.applyFilters());
      }
    },

    applyFilters() {
      const query = this.searchInput.value.toLowerCase().trim();
      const role = this.roleFilter.value;

      this.cards.forEach(card => {
        const name = card.dataset.name;
        const subject = card.dataset.subject;
        const cardRole = card.dataset.role;

        const matchesSearch = query === '' || name.includes(query) || subject.includes(query);
        const matchesRole = role === 'all' || cardRole === role;

        card.style.display = (matchesSearch && matchesRole) ? '' : 'none';
      });
    },

    applySort() {
      const sortBy = this.sortSelect.value;
      const visibleCards = this.cards.slice();

      visibleCards.sort((a, b) => {
        switch (sortBy) {
          case 'name-asc':
            return a.dataset.name.localeCompare(b.dataset.name);
          case 'name-desc':
            return b.dataset.name.localeCompare(a.dataset.name);
          case 'members':
            return parseInt(b.dataset.membersCount) - parseInt(a.dataset.membersCount);
          case 'activity':
            return parseInt(b.dataset.activity) - parseInt(a.dataset.activity);
          case 'date-desc':
            return new Date(b.dataset.created) - new Date(a.dataset.created);
          default:
            return 0;
        }
      });

      // Detach and re-append in order
      visibleCards.forEach(card => {
        this.container.appendChild(card);
      });

      // Keep "Join group card" at the end
      const joinCard = this.container.querySelector('[data-modal-open="join-group-modal"]');
      if (joinCard) {
        this.container.appendChild(joinCard);
      }
    }
  };

  const ShareManagement = {
    init() {
      const shareModal = document.querySelector('[data-share-modal]');
      if (!shareModal) return;

      const shareBtns = document.querySelectorAll('[data-share-btn]');
      const shareCopyBtn = document.querySelector('[data-share-copy]');
      const shareFacebookBtn = document.querySelector('[data-share-facebook]');
      const shareTwitterBtn = document.querySelector('[data-share-twitter]');

      let currentSharePostId = null;

      shareBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          currentSharePostId = btn.dataset.postId;
          Modal.open('share-modal'); // Assuming there's a modal ID or using data-share-modal
        });
      });

      if (shareCopyBtn) {
        shareCopyBtn.addEventListener('click', (e) => {
          e.preventDefault();
          const postUrl = `${window.location.origin}${window.location.pathname}#post-${currentSharePostId}`;
          navigator.clipboard.writeText(postUrl).then(() => {
            Toast.success('Succès', 'Lien copié dans le presse-papiers!');
            Modal.closeAll();
          });
        });
      }

      if (shareFacebookBtn) {
        shareFacebookBtn.addEventListener('click', (e) => {
          e.preventDefault();
          const postUrl = `${window.location.origin}${window.location.pathname}#post-${currentSharePostId}`;
          const facebookUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(postUrl)}`;
          window.open(facebookUrl, 'facebook-share', 'width=600,height=400');
        });
      }

      if (shareTwitterBtn) {
        shareTwitterBtn.addEventListener('click', (e) => {
          e.preventDefault();
          const postUrl = `${window.location.origin}${window.location.pathname}#post-${currentSharePostId}`;
          const twitterUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(postUrl)}&text=Décourez ce post intéressant!`;
          window.open(twitterUrl, 'twitter-share', 'width=600,height=400');
        });
      }
    }
  };

  // ─── CHATBOT CONFIG MANAGEMENT ───
  const ChatbotConfig = {
    groupId: null,
    modal: null,
    backdrop: null,

    init() {
      const widget = document.getElementById('chatbot-config-widget');
      if (!widget) return;

      this.groupId = widget.dataset.groupId;

      // Open config modal
      document.querySelectorAll('#chatbot-open-config').forEach(btn => {
        btn.addEventListener('click', () => this.openModal());
      });

      // Save config
      const saveBtn = document.getElementById('chatbot-config-save');
      if (saveBtn) saveBtn.addEventListener('click', () => this.saveConfig());

      // Toggle bot on/off from sidebar
      const toggle = document.getElementById('chatbot-toggle');
      if (toggle) {
        toggle.addEventListener('change', () => this.toggleBot());
      }

      // Trigger mode shows/hides keywords
      const triggerMode = document.getElementById('cfg-triggerMode');
      if (triggerMode) {
        triggerMode.addEventListener('change', (e) => {
          const kwGroup = document.getElementById('cfg-keywords-group');
          if (kwGroup) {
            kwGroup.style.display = ['mention', 'keyword'].includes(e.target.value) ? 'block' : 'none';
          }
        });
      }

      // Max response length slider label
      const slider = document.getElementById('cfg-maxResponseLength');
      if (slider) {
        slider.addEventListener('input', (e) => {
          const display = document.getElementById('cfg-maxLen-display');
          if (display) display.textContent = e.target.value + ' car.';
        });
      }

      // Personality highlight
      document.querySelectorAll('.chatbot-personality-option input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', () => {
          document.querySelectorAll('.chatbot-personality-option').forEach(opt => {
            opt.style.borderColor = 'var(--border-color)';
            opt.style.background = 'transparent';
          });
          const selected = document.querySelector('.chatbot-personality-option input[type="radio"]:checked');
          if (selected) {
            const parent = selected.closest('.chatbot-personality-option');
            parent.style.borderColor = '#6366F1';
            parent.style.background = 'rgba(99, 102, 241, 0.04)';
          }
        });
      });

      // Highlight initial selection
      const checkedPersonality = document.querySelector('.chatbot-personality-option input[type="radio"]:checked');
      if (checkedPersonality) {
        const parent = checkedPersonality.closest('.chatbot-personality-option');
        if (parent) {
          parent.style.borderColor = '#6366F1';
          parent.style.background = 'rgba(99, 102, 241, 0.04)';
        }
      }

      // Language radio grid
      document.querySelectorAll('.cfg-lang-option input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', () => {
          document.querySelectorAll('.cfg-lang-option').forEach(opt => {
            opt.style.borderColor = 'var(--border-color, #e5e7eb)';
            opt.style.background = 'transparent';
          });
          const selected = radio.closest('.cfg-lang-option');
          if (selected) {
            selected.style.borderColor = '#6366F1';
            selected.style.background = 'rgba(99, 102, 241, 0.06)';
          }
          // Sync hidden input
          const hidden = document.getElementById('cfg-language');
          if (hidden) hidden.value = radio.value;
        });
      });

      // Toggle switch styling for modal
      this.initToggleSwitchStyle();
    },

    initToggleSwitchStyle() {
      document.querySelectorAll('#chatbot-toggle').forEach(cb => {
        const label = cb.closest('label');
        const slider = cb.nextElementSibling;
        if (!slider) return;
        const dot = slider.querySelector('.toggle-dot') || slider.querySelector('span');

        const updateStyle = () => {
          slider.style.backgroundColor = cb.checked ? '#6366F1' : '#cbd5e1';
          if (dot) {
            dot.style.left = cb.checked ? '21px' : '3px';
          }
        };

        updateStyle();
        cb.addEventListener('change', updateStyle);

        // If not inside a label, add explicit click handler
        if (!label) {
          slider.addEventListener('click', (e) => {
            e.preventDefault();
            cb.checked = !cb.checked;
            cb.dispatchEvent(new Event('change'));
          });
        }
      });
    },

    async openModal() {
      // Close any open drawer or other modal first
      Drawer.closeAll();
      Modal.closeAll();
      // Load current config from API BEFORE showing modal
      await this.loadConfig();
      Modal.open('chatbot-config-modal');
    },

    closeModal() {
      Modal.close('chatbot-config-modal');
    },

    async loadConfig() {
      try {
        const response = await fetch(`/app/api/chatbot/groups/${this.groupId}/config`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();
        if (data.success && data.isConfigured) {
          const c = data.config;
          const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };
          setVal('cfg-botName', c.botName);
          setVal('cfg-subjectContext', c.subjectContext);
          setVal('cfg-triggerMode', c.triggerMode);
          setVal('cfg-triggerKeywords', (c.triggerKeywords || []).join(', '));

          // Select the correct language radio
          const langRadio = document.querySelector(`input[name="language"][value="${c.language || 'fr'}"]`);
          if (langRadio) {
            langRadio.checked = true;
            langRadio.dispatchEvent(new Event('change'));
          }
          setVal('cfg-language', c.language);
          setVal('cfg-maxResponseLength', c.maxResponseLength);

          const maxDisplay = document.getElementById('cfg-maxLen-display');
          if (maxDisplay) maxDisplay.textContent = (c.maxResponseLength || 800) + ' car.';

          const personality = document.querySelector(`input[name="personality"][value="${c.personality}"]`);
          if (personality) {
            personality.checked = true;
            personality.dispatchEvent(new Event('change'));
          }

          // Show/hide keywords
          const kwGroup = document.getElementById('cfg-keywords-group');
          if (kwGroup) kwGroup.style.display = ['mention', 'keyword'].includes(c.triggerMode) ? 'block' : 'none';
        }
      } catch (e) {
        console.error('Failed to load chatbot config', e);
      }
    },

    async saveConfig() {
      const saveBtn = document.getElementById('chatbot-config-save');
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-sm"></span> Sauvegarde...';
      }

      try {
        const form = document.getElementById('chatbot-config-form');
        const personality = form.querySelector('input[name="personality"]:checked');
        const keywordsStr = document.getElementById('cfg-triggerKeywords')?.value || '';
        const keywords = keywordsStr.split(',').map(k => k.trim()).filter(k => k.length > 0);

        const configData = {
          botName: document.getElementById('cfg-botName')?.value || 'StudyBot',
          personality: personality?.value || 'tutor',
          subjectContext: document.getElementById('cfg-subjectContext')?.value || '',
          triggerMode: document.getElementById('cfg-triggerMode')?.value || 'auto-detect',
          triggerKeywords: keywords,
          language: (form.querySelector('input[name="language"]:checked') || document.getElementById('cfg-language'))?.value || 'fr',
          maxResponseLength: parseInt(document.getElementById('cfg-maxResponseLength')?.value || '800'),
        };

        const response = await fetch(`/app/api/chatbot/groups/${this.groupId}/config`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify(configData),
        });

        const data = await response.json();

        if (data.success) {
          Toast.success('Chatbot', 'Configuration sauvegardée avec succès');
          this.closeModal();
          this.updateSidebarWidget(data.config);
        } else {
          Toast.error('Erreur', data.error || 'Impossible de sauvegarder la configuration');
        }
      } catch (e) {
        Toast.error('Erreur', 'Impossible de sauvegarder la configuration');
        console.error(e);
      } finally {
        if (saveBtn) {
          saveBtn.disabled = false;
          saveBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M20 6L9 17l-5-5"/></svg> Sauvegarder';
        }
      }
    },

    async toggleBot() {
      try {
        const response = await fetch(`/app/api/chatbot/groups/${this.groupId}/toggle`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
        });

        const data = await response.json();

        if (data.success) {
          const dot = document.getElementById('chatbot-status-dot');
          const text = document.getElementById('chatbot-status-text');
          if (dot) dot.style.background = data.isEnabled ? '#22c55e' : '#9ca3af';
          if (text) text.textContent = data.isEnabled ? 'Activé' : 'Désactivé';
          Toast.success('Chatbot', data.message);
        } else {
          Toast.error('Erreur', data.error || 'Impossible de changer le statut');
          // Revert toggle visually without triggering toggleBot() again
          const toggle = document.getElementById('chatbot-toggle');
          if (toggle) {
            toggle.checked = !toggle.checked;
            const slider = toggle.nextElementSibling;
            if (slider) {
              slider.style.backgroundColor = toggle.checked ? '#6366F1' : '#cbd5e1';
              const dot = slider.querySelector('.toggle-dot') || slider.querySelector('span');
              if (dot) dot.style.left = toggle.checked ? '21px' : '3px';
            }
          }
        }
      } catch (e) {
        Toast.error('Erreur', 'Impossible de changer le statut');
        const toggle = document.getElementById('chatbot-toggle');
        if (toggle) {
          toggle.checked = !toggle.checked;
          const slider = toggle.nextElementSibling;
          if (slider) {
            slider.style.backgroundColor = toggle.checked ? '#6366F1' : '#cbd5e1';
            const dot = slider.querySelector('.toggle-dot') || slider.querySelector('span');
            if (dot) dot.style.left = toggle.checked ? '21px' : '3px';
          }
        }
      }
    },

    updateSidebarWidget(config) {
      // Update sidebar display values
      const nameEl = document.getElementById('chatbot-display-name');
      if (nameEl) nameEl.textContent = config.botName || 'StudyBot';

      const personalityMap = { tutor: 'Tuteur', assistant: 'Assistant', mentor: 'Mentor', 'quiz-master': 'Quiz Master' };
      const personalityEl = document.getElementById('chatbot-display-personality');
      if (personalityEl) personalityEl.textContent = personalityMap[config.personality] || config.personality;

      const triggerMap = { 'auto-detect': 'Auto (questions)', mention: 'Par mention', keyword: 'Par mot-clé' };
      const triggerEl = document.getElementById('chatbot-display-trigger');
      if (triggerEl) triggerEl.textContent = triggerMap[config.triggerMode] || config.triggerMode;

      const langFlagMap = { fr: 'fr', en: 'gb', es: 'es', de: 'de', ar: 'sa', it: 'it', pt: 'pt', tr: 'tr', zh: 'cn' };
      const langLabelMap = { fr: 'Français', en: 'English', es: 'Español', de: 'Deutsch', ar: 'العربية', it: 'Italiano', pt: 'Português', tr: 'Türkçe', zh: '中文' };
      const langEl = document.getElementById('chatbot-display-language');
      if (langEl) {
        const flag = langFlagMap[config.language] || 'fr';
        const label = langLabelMap[config.language] || 'Français';
        langEl.innerHTML = `<span class="fi fi-${flag}" style="font-size: 14px;"></span> ${label}`;
      }

      const dot = document.getElementById('chatbot-status-dot');
      const text = document.getElementById('chatbot-status-text');
      if (dot) dot.style.background = config.isEnabled ? '#22c55e' : '#9ca3af';
      if (text) text.textContent = config.isEnabled ? 'Activé' : 'Désactivé';

      const toggle = document.getElementById('chatbot-toggle');
      if (toggle) {
        // Update visually without triggering the toggleBot() handler
        toggle.checked = config.isEnabled;
        const slider = toggle.nextElementSibling;
        if (slider) {
          slider.style.backgroundColor = config.isEnabled ? '#6366F1' : '#cbd5e1';
          const dot = slider.querySelector('.toggle-dot') || slider.querySelector('span');
          if (dot) dot.style.left = config.isEnabled ? '21px' : '3px';
        }
      }

      // If page needs refresh for first-time config, reload
      if (!document.getElementById('chatbot-status-dot')) {
        window.location.reload();
      }
    }
  };

  // ─── INIT ALL ───
  document.addEventListener('DOMContentLoaded', () => {
    Drawer.init();
    Modal.init();
    Toast.init();
    Tabs.init();
    Dropdown.init();
    MemberManagement.init();
    GroupManagement.init();
    GroupSearch.init();
    ConfirmDialog.init();
    TodoCheckbox.init();
    ShareManagement.init();
    GroupDashboard.init();
    ChatbotConfig.init();

    // Init PostManagement if we are on a group detail page
    const groupContainer = document.querySelector('[data-group-id]');
    if (groupContainer) {
      PostManagement.init(groupContainer.dataset.groupId);
    }

    // Check for state param and show toast if success
    const state = UrlState.get('state');
    if (state === 'success') {
      Toast.success('Succes', 'Action effectuee avec succes.');
    }
  });

  // Expose to global
  window.StudySprint = {
    Drawer,
    Modal,
    Toast,
    Tabs,
    Dropdown,
    MemberManagement,
    GroupManagement,
    ConfirmDialog,
    UrlState
  };

})();