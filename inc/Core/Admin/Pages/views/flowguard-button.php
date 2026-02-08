<?php
/**
 * FlowGuard Floating Action Button
 * Toggles the FlowGuard debug panel
 */
?>

<!-- Floating Action Button -->
<div id="flowguard-toggle-btn" style="position: fixed; bottom: 20px; right: 20px; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 9999; transition: all 0.3s ease; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);" title="FlowGuard Info">
    <span class="dashicons dashicons-info" style="color: #fff; font-size: 28px; width: auto; height: auto;"></span>
</div>

<style>
#flowguard-toggle-btn:hover {
    background: #135e96;
    box-shadow: 0 6px 16px rgba(34, 113, 177, 0.5);
    transform: scale(1.05);
}

#flowguard-toggle-btn:active {
    transform: scale(0.95);
}

/* Tooltip styling */
#flowguard-toggle-btn::after {
    content: attr(title);
    position: absolute;
    right: 100%;
    margin-right: 12px;
    background: #1e293b;
    color: #fff;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
}

#flowguard-toggle-btn:hover::after {
    opacity: 1;
}

/* Fade animations for panel */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeOut {
    from {
        opacity: 1;
        transform: translateY(0);
    }
    to {
        opacity: 0;
        transform: translateY(10px);
    }
}

.flowguard-fade-in {
    animation: fadeIn 0.3s ease forwards;
}

.flowguard-fade-out {
    animation: fadeOut 0.3s ease forwards;
}

/* Mobile responsiveness */
@media screen and (max-width: 768px) {
    #flowguard-debug-panel {
        right: 10px !important;
        left: 10px !important;
        width: auto !important;
        max-width: calc(100vw - 20px) !important;
    }

    #flowguard-toggle-btn {
        bottom: 15px;
        right: 15px;
        width: 48px;
        height: 48px;
    }
    
    #flowguard-toggle-btn .dashicons {
        font-size: 24px;
    }
}
</style>

<script>
(function() {
    // Use jQuery if available, otherwise vanilla JS
    const $ = window.jQuery;
    
    /**
     * Fetch fresh FlowGuard state from server
     */
    async function fetchFlowGuardState() {
        const flowGuardUrl = window.rcRegistration?.flowGuardStateUrl;
        const nonce = window.rcRegistration?.nonce;
        
        if (!flowGuardUrl || !nonce) {
            console.warn('FlowGuard: Missing URL or nonce');
            return null;
        }
        
        try {
            const response = await fetch(flowGuardUrl, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': nonce,
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                console.warn('FlowGuard: Failed to fetch state', response.status);
                return null;
            }
            
            return await response.json();
        } catch (e) {
            console.warn('FlowGuard: Error fetching state', e);
            return null;
        }
    }
    
    /**
     * Update panel HTML with fresh state data
     */
    function updatePanelContent(state, panelElement) {
        if (!state || !panelElement) return;
        
        const channel = (state.channel || 'direct').toUpperCase();
        const isRegistered = state.registered === true;
        const isEmailVerified = state.emailVerified === true;
        const isActivated = state.activated === true;
        const isOnboarded = state.onboarded === true;
        const progress = state.progress || 0;
        const isDirect = state.channel === 'direct';
        
        // Update channel name in header
        const headerText = panelElement.querySelector('.flowguard-header-text');
        if (headerText) {
            headerText.textContent = 'Flow status for ' + channel + ' channel';
        }
        
        // Update step 1: Registration
        const step1Circle = panelElement.querySelector('[data-step="register"]');
        const step1Label = panelElement.querySelector('[data-step-label="register"]');
        if (step1Circle) {
            step1Circle.style.background = isRegistered ? '#10b981' : '#e5e7eb';
            step1Circle.style.color = isRegistered ? '#fff' : '#6b7280';
            step1Circle.innerHTML = isRegistered
                ? '<span class="dashicons dashicons-yes" style="font-size: 20px;"></span>'
                : '1';
        }
        if (step1Label) {
            step1Label.style.color = isRegistered ? '#10b981' : '#6b7280';
        }
        
        // Update step 2: Email Verification (direct channel only)
        if (isDirect) {
            const step2Circle = panelElement.querySelector('[data-step="email"]');
            const step2Label = panelElement.querySelector('[data-step-label="email"]');
            if (step2Circle) {
                step2Circle.style.background = isEmailVerified ? '#10b981' : '#e5e7eb';
                step2Circle.style.color = isEmailVerified ? '#fff' : '#6b7280';
                step2Circle.innerHTML = isEmailVerified
                    ? '<span class="dashicons dashicons-yes" style="font-size: 20px;"></span>'
                    : '<span class="dashicons dashicons-email" style="font-size: 16px;"></span>';
            }
            if (step2Label) {
                step2Label.style.color = isEmailVerified ? '#10b981' : '#6b7280';
            }
        }
        
        // Update step 3: Activation
        const step3Circle = panelElement.querySelector('[data-step="activate"]');
        const step3Label = panelElement.querySelector('[data-step-label="activate"]');
        if (step3Circle) {
            step3Circle.style.background = isActivated ? '#10b981' : '#e5e7eb';
            step3Circle.style.color = isActivated ? '#fff' : '#6b7280';
            step3Circle.innerHTML = isActivated
                ? '<span class="dashicons dashicons-yes" style="font-size: 20px;"></span>'
                : (isDirect ? '3' : '2');
        }
        if (step3Label) {
            step3Label.style.color = isActivated ? '#10b981' : '#6b7280';
        }
        
        // Update step 4: Onboarding
        const step4Circle = panelElement.querySelector('[data-step="onboard"]');
        const step4Label = panelElement.querySelector('[data-step-label="onboard"]');
        if (step4Circle) {
            step4Circle.style.background = isOnboarded ? '#10b981' : '#e5e7eb';
            step4Circle.style.color = isOnboarded ? '#fff' : '#6b7280';
            step4Circle.innerHTML = isOnboarded
                ? '<span class="dashicons dashicons-yes" style="font-size: 20px;"></span>'
                : (isDirect ? '4' : '3');
        }
        if (step4Label) {
            step4Label.style.color = isOnboarded ? '#10b981' : '#6b7280';
        }
        
        // Update progress bar
        const progressBar = panelElement.querySelector('[data-progress-bar]');
        if (progressBar) {
            progressBar.style.width = progress + '%';
        }
    }
    
    if ($) {
        // jQuery version
        $(document).ready(function() {
            const $toggleBtn = $('#flowguard-toggle-btn');
            const $panel = $('#flowguard-debug-panel');
            const $closeBtn = $('#flowguard-close-btn');
            
            if (!$toggleBtn.length || !$panel.length) {
                return;
            }
            
            // Toggle panel on button click
            $toggleBtn.on('click', async function() {
                if ($panel.is(':visible')) {
                    $panel.removeClass('flowguard-fade-in').addClass('flowguard-fade-out');
                    setTimeout(function() {
                        $panel.hide().removeClass('flowguard-fade-out');
                    }, 300);
                } else {
                    // Fetch fresh state before showing panel
                    const state = await fetchFlowGuardState();
                    if (state) {
                        updatePanelContent(state, $panel[0]);
                    }
                    $panel.show().removeClass('flowguard-fade-out').addClass('flowguard-fade-in');
                }
            });
            
            // Close panel on close button click
            $closeBtn.on('click', function() {
                $panel.removeClass('flowguard-fade-in').addClass('flowguard-fade-out');
                setTimeout(function() {
                    $panel.hide().removeClass('flowguard-fade-out');
                }, 300);
            });
            
            // Close panel on Escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $panel.is(':visible')) {
                    $panel.removeClass('flowguard-fade-in').addClass('flowguard-fade-out');
                    setTimeout(function() {
                        $panel.hide().removeClass('flowguard-fade-out');
                    }, 300);
                }
            });
        });
    } else {
        // Vanilla JS version
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('flowguard-toggle-btn');
            const panel = document.getElementById('flowguard-debug-panel');
            const closeBtn = document.getElementById('flowguard-close-btn');
            
            if (!toggleBtn || !panel) {
                return;
            }
            
            // Toggle panel on button click
            toggleBtn.addEventListener('click', async function() {
                if (panel.style.display === 'none' || !panel.style.display) {
                    // Fetch fresh state before showing panel
                    const state = await fetchFlowGuardState();
                    if (state) {
                        updatePanelContent(state, panel);
                    }
                    panel.style.display = 'block';
                    panel.classList.remove('flowguard-fade-out');
                    panel.classList.add('flowguard-fade-in');
                } else {
                    panel.classList.remove('flowguard-fade-in');
                    panel.classList.add('flowguard-fade-out');
                    setTimeout(function() {
                        panel.style.display = 'none';
                        panel.classList.remove('flowguard-fade-out');
                    }, 300);
                }
            });
            
            // Close panel on close button click
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    panel.classList.remove('flowguard-fade-in');
                    panel.classList.add('flowguard-fade-out');
                    setTimeout(function() {
                        panel.style.display = 'none';
                        panel.classList.remove('flowguard-fade-out');
                    }, 300);
                });
            }
            
            // Close panel on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && panel.style.display !== 'none') {
                    panel.classList.remove('flowguard-fade-in');
                    panel.classList.add('flowguard-fade-out');
                    setTimeout(function() {
                        panel.style.display = 'none';
                        panel.classList.remove('flowguard-fade-out');
                    }, 300);
                }
            });
        });
    }
})();
</script>