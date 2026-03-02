/**
 * StudySprint - Post Translation via Lingva Translate API
 * Handles translate button toggle, language selection, and API calls.
 */
document.addEventListener('DOMContentLoaded', () => {
    if (window.__translateInitialized) return;
    window.__translateInitialized = true;
    initTranslation();
});

function initTranslation() {
    // Toggle translate dropdown on button click
    document.addEventListener('click', (e) => {
        const toggleBtn = e.target.closest('[data-translate-toggle]');
        if (toggleBtn) {
            e.stopPropagation();
            const postId = toggleBtn.getAttribute('data-translate-toggle');
            const dropdown = document.querySelector(`[data-translate-dropdown="${postId}"]`);

            // Close all other dropdowns
            document.querySelectorAll('[data-translate-dropdown]').forEach(d => {
                if (d !== dropdown) d.style.display = 'none';
            });

            // Toggle this dropdown
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
            return;
        }

        // Language button click → trigger translation
        const langBtn = e.target.closest('[data-translate-post]');
        if (langBtn) {
            e.stopPropagation();
            const postId = langBtn.getAttribute('data-translate-post');
            const lang = langBtn.getAttribute('data-lang');
            translatePost(postId, lang);

            // Close dropdown
            const dropdown = document.querySelector(`[data-translate-dropdown="${postId}"]`);
            if (dropdown) dropdown.style.display = 'none';
            return;
        }

        // Close translation result (posts)
        const closeBtn = e.target.closest('[data-translation-close]');
        if (closeBtn) {
            const postId = closeBtn.getAttribute('data-translation-close');
            const result = document.querySelector(`[data-translation-result="${postId}"]`);
            if (result) result.style.display = 'none';
            return;
        }

        // ─── COMMENT TRANSLATION HANDLERS ───

        // Toggle comment translate dropdown
        const commentToggleBtn = e.target.closest('[data-translate-comment-toggle]');
        if (commentToggleBtn) {
            e.stopPropagation();
            const commentId = commentToggleBtn.getAttribute('data-translate-comment-toggle');
            const dropdown = document.querySelector(`[data-translate-comment-dropdown="${commentId}"]`);

            // Close all other comment dropdowns
            document.querySelectorAll('[data-translate-comment-dropdown]').forEach(d => {
                if (d !== dropdown) d.style.display = 'none';
            });
            // Close all post dropdowns too
            document.querySelectorAll('[data-translate-dropdown]').forEach(d => {
                d.style.display = 'none';
            });

            if (dropdown) {
                dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
            }
            return;
        }

        // Comment language button click → trigger comment translation
        const commentLangBtn = e.target.closest('[data-translate-comment]');
        if (commentLangBtn) {
            e.stopPropagation();
            const commentId = commentLangBtn.getAttribute('data-translate-comment');
            const lang = commentLangBtn.getAttribute('data-lang');
            translateComment(commentId, lang);

            // Close dropdown
            const dropdown = document.querySelector(`[data-translate-comment-dropdown="${commentId}"]`);
            if (dropdown) dropdown.style.display = 'none';
            return;
        }

        // Close comment translation result
        const commentCloseBtn = e.target.closest('[data-comment-translation-close]');
        if (commentCloseBtn) {
            const commentId = commentCloseBtn.getAttribute('data-comment-translation-close');
            const result = document.querySelector(`[data-comment-translation-result="${commentId}"]`);
            if (result) result.style.display = 'none';
            return;
        }

        // Click outside → close all dropdowns (posts + comments)
        document.querySelectorAll('[data-translate-dropdown]').forEach(d => {
            d.style.display = 'none';
        });
        document.querySelectorAll('[data-translate-comment-dropdown]').forEach(d => {
            d.style.display = 'none';
        });
    });

    // Hover effect on language buttons
    document.addEventListener('mouseover', (e) => {
        const langBtn = e.target.closest('.fo-translate-lang-btn');
        if (langBtn) {
            langBtn.style.background = 'var(--color-gray-100, #f3f4f6)';
        }
    });
    document.addEventListener('mouseout', (e) => {
        const langBtn = e.target.closest('.fo-translate-lang-btn');
        if (langBtn) {
            langBtn.style.background = 'none';
        }
    });
}

/**
 * Helper: call translation API
 */
async function callTranslateApi(text, targetLang) {
    const response = await fetch('/app/api/translate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            text: text,
            target: targetLang,
            source: 'auto',
        }),
    });
    return response.json();
}

async function translatePost(postId, targetLang) {
    const contentEl = document.querySelector(`[data-post-content="${postId}"]`);
    const titleEl = document.querySelector(`[data-post-title="${postId}"]`);
    const resultEl = document.querySelector(`[data-translation-result="${postId}"]`);
    const textEl = document.querySelector(`[data-translation-text="${postId}"]`);
    const titleResultEl = document.querySelector(`[data-translation-title="${postId}"]`);

    if (!contentEl || !resultEl || !textEl) {
        console.error('Translation: missing DOM elements for post', postId);
        return;
    }

    const bodyText = contentEl.textContent.trim();
    const titleText = titleEl ? titleEl.textContent.trim() : '';

    if (!bodyText && !titleText) return;

    // Show loading state
    resultEl.style.display = 'block';
    textEl.textContent = 'Traduction en cours...';
    textEl.style.opacity = '0.5';
    textEl.style.fontStyle = 'italic';
    if (titleResultEl) {
        titleResultEl.style.display = 'none';
        titleResultEl.textContent = '';
    }

    try {
        // Translate body and title in parallel
        const promises = [];
        promises.push(bodyText ? callTranslateApi(bodyText, targetLang) : Promise.resolve(null));
        promises.push(titleText ? callTranslateApi(titleText, targetLang) : Promise.resolve(null));

        const [bodyData, titleData] = await Promise.all(promises);

        // Body result
        if (bodyData && bodyData.success && bodyData.translation) {
            textEl.textContent = bodyData.translation;
            textEl.style.opacity = '1';
            textEl.style.fontStyle = 'normal';
        } else if (bodyData) {
            textEl.textContent = '❌ ' + (bodyData.error || 'Échec de la traduction');
            textEl.style.opacity = '1';
            textEl.style.fontStyle = 'normal';
            textEl.style.color = 'var(--color-error, #EF4444)';
            setTimeout(() => { textEl.style.color = ''; }, 3000);
        } else {
            textEl.textContent = '';
            textEl.style.opacity = '1';
            textEl.style.fontStyle = 'normal';
        }

        // Title result
        if (titleResultEl && titleData && titleData.success && titleData.translation) {
            titleResultEl.textContent = titleData.translation;
            titleResultEl.style.display = 'block';
        }

    } catch (error) {
        console.error('Translation error:', error);
        textEl.textContent = '❌ Erreur de connexion au service de traduction';
        textEl.style.opacity = '1';
        textEl.style.fontStyle = 'normal';
    }
}

/**
 * Translate a comment body
 */
async function translateComment(commentId, targetLang) {
    const contentEl = document.querySelector(`[data-comment-content="${commentId}"]`);
    const resultEl = document.querySelector(`[data-comment-translation-result="${commentId}"]`);
    const textEl = document.querySelector(`[data-comment-translation-text="${commentId}"]`);

    if (!contentEl || !resultEl || !textEl) {
        console.error('Translation: missing DOM elements for comment', commentId);
        return;
    }

    const text = contentEl.textContent.trim();
    if (!text) return;

    // Show loading state
    resultEl.style.display = 'block';
    textEl.textContent = 'Traduction en cours...';
    textEl.style.opacity = '0.5';
    textEl.style.fontStyle = 'italic';
    textEl.style.color = '';

    try {
        const data = await callTranslateApi(text, targetLang);

        if (data.success && data.translation) {
            textEl.textContent = data.translation;
            textEl.style.opacity = '1';
            textEl.style.fontStyle = 'normal';
        } else {
            textEl.textContent = '❌ ' + (data.error || 'Échec de la traduction');
            textEl.style.opacity = '1';
            textEl.style.fontStyle = 'normal';
            textEl.style.color = 'var(--color-error, #EF4444)';
            setTimeout(() => { textEl.style.color = ''; }, 3000);
        }
    } catch (error) {
        console.error('Comment translation error:', error);
        textEl.textContent = '❌ Erreur de connexion au service de traduction';
        textEl.style.opacity = '1';
        textEl.style.fontStyle = 'normal';
    }
}
