<?php
/**
 * FlowGuard Debug Panel - Compact floating panel
 * 
 * @var array|null $channelMeta Channel metadata
 * @var object|null $flowState Flow state object
 */

// Only display if FlowGuard is enabled and data exists
if (empty($channelMeta) && empty($flowState)) {
    return;
}

$channel = $channelMeta['channel'] ?? 'direct';
$detectedAt = isset($channelMeta['detected_at']) ? (int)$channelMeta['detected_at'] : null;
$proofs = $channelMeta['proofs'] ?? [];

$isRegistered = isset($flowState->registered) ? (bool)$flowState->registered : false;
$isEmailVerified = isset($flowState->emailVerified) ? (bool)$flowState->emailVerified : false;
$isActivated = isset($flowState->activated) ? (bool)$flowState->activated : false;
$isOnboarded = isset($flowState->onboarded) ? (bool)$flowState->onboarded : false;
$flowMeta = isset($flowState->meta) ? (array)$flowState->meta : [];

// Calculate progress percentage
// For direct channel: Register(25%) -> Email(50%) -> Activate(75%) -> Onboard(100%)
// For other channels: Register(33%) -> Activate(66%) -> Onboard(100%)
$progress = 0;
if ($channel === 'direct') {
    if ($isOnboarded) {
        $progress = 100;
    } elseif ($isActivated) {
        $progress = 75;
    } elseif ($isEmailVerified) {
        $progress = 50;
    } elseif ($isRegistered) {
        $progress = 25;
    }
} else {
    if ($isOnboarded) {
        $progress = 100;
    } elseif ($isActivated) {
        $progress = 66;
    } elseif ($isRegistered) {
        $progress = 33;
    }
}

// Channel badge colors
$channelColors = [
    'ionos' => ['bg' => '#003d7a', 'text' => '#ffffff'],
    'extendify' => ['bg' => '#6366f1', 'text' => '#ffffff'],
    'direct' => ['bg' => '#16a34a', 'text' => '#ffffff'],
];
$channelColor = $channelColors[$channel] ?? ['bg' => '#6b7280', 'text' => '#ffffff'];
?>

<div id="flowguard-debug-panel" style="display: none; position: fixed; bottom: 20px; right: 80px; width: 350px; max-height: 500px; background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 9998; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;">
    <!-- Header -->
    <div style="color: #fff; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between;     background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="dashicons dashicons-shield-alt" style="font-size: 18px;"></span>
            <span class="flowguard-header-text" style="font-weight: 600; font-size: 14px;">Flow status for <?php echo esc_html(strtoupper($channel)); ?> channel</span>
        </div>
        <button type="button" id="flowguard-close-btn" style="background: none; border: none; color: #fff; cursor: pointer; padding: 0; font-size: 20px; line-height: 1;" aria-label="Close FlowGuard panel">
            <span class="dashicons dashicons-no-alt" style="font-size: 20px;"></span>
        </button>
    </div>
    
    <!-- Content with scroll -->
    <div style="padding: 16px; max-height: 432px; overflow-y: auto;">
        <!-- Channel Badge -->
        <div style="margin-bottom: 16px; display: none;">
            <div style="font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Channel</div>
            <?php if (!empty($proofs) && is_array($proofs)): ?>
            <div style="margin-top: 8px; font-size: 11px; color: #6b7280;">
                <strong>Evidence:</strong>
                <ul style="margin: 4px 0 0 0; padding-left: 18px;">
                    <?php foreach ($proofs as $proof): ?>
                        <li style="margin: 2px 0;"><?php echo esc_html(is_array($proof) ? json_encode($proof) : $proof); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Flow Progress - Horizontal Compact -->
        <div style="margin-bottom: 16px;">
            <div style="font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Flow Progress</div>
            
            <!-- Progress Steps -->
            <div style="display: flex; align-items: center; gap: 4px; margin-bottom: 8px;">
                <!-- Registration -->
                <div style="flex: 1; text-align: center;">
                    <div data-step="register" style="width: 32px; height: 32px; margin: 0 auto 4px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: <?php echo $isRegistered ? '#10b981' : '#e5e7eb'; ?>; color: <?php echo $isRegistered ? '#fff' : '#6b7280'; ?>; font-weight: 600; font-size: 14px;">
                        <?php if ($isRegistered): ?>
                            <span class="dashicons dashicons-yes" style="font-size: 20px;"></span>
                        <?php else: ?>
                            1
                        <?php endif; ?>
                    </div>
                    <div data-step-label="register" style="font-size: 10px; font-weight: 600; color: <?php echo $isRegistered ? '#10b981' : '#6b7280'; ?>;">
                        Register
                    </div>
                </div>
                
                <?php if ($channel === 'direct'): ?>
                <div style="flex: 0 0 auto; color: #cbd5e1; font-size: 16px;">→</div>
                
                <!-- Email Verification (Direct channel only) -->
                <div style="flex: 1; text-align: center;">
                    <div data-step="email" style="width: 32px; height: 32px; margin: 0 auto 4px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: <?php echo $isEmailVerified ? '#10b981' : '#e5e7eb'; ?>; color: <?php echo $isEmailVerified ? '#fff' : '#6b7280'; ?>; font-weight: 600; font-size: 14px;">
                        <?php if ($isEmailVerified): ?>
                            <span class="dashicons dashicons-yes" style="font-size: 20px;"></span>
                        <?php else: ?>
                            <span class="dashicons dashicons-email" style="font-size: 16px;"></span>
                        <?php endif; ?>
                    </div>
                    <div data-step-label="email" style="font-size: 10px; font-weight: 600; color: <?php echo $isEmailVerified ? '#10b981' : '#6b7280'; ?>;">
                        Verify Email
                    </div>
                </div>
                <?php endif; ?>
                
                <div style="flex: 0 0 auto; color: #cbd5e1; font-size: 16px;">→</div>
                
                <!-- Activation -->
                <div style="flex: 1; text-align: center;">
                    <div data-step="activate" style="width: 32px; height: 32px; margin: 0 auto 4px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: <?php echo $isActivated ? '#10b981' : '#e5e7eb'; ?>; color: <?php echo $isActivated ? '#fff' : '#6b7280'; ?>; font-weight: 600; font-size: 14px;">
                        <?php if ($isActivated): ?>
                            <span class="dashicons dashicons-yes" style="font-size: 20px;"></span>
                        <?php else: ?>
                            <?php echo $channel === 'direct' ? '3' : '2'; ?>
                        <?php endif; ?>
                    </div>
                    <div data-step-label="activate" style="font-size: 10px; font-weight: 600; color: <?php echo $isActivated ? '#10b981' : '#6b7280'; ?>;">
                        Activate
                    </div>
                </div>
                
                <div style="flex: 0 0 auto; color: #cbd5e1; font-size: 16px;">→</div>
                
                <!-- Onboarding -->
                <div style="flex: 1; text-align: center;">
                    <div data-step="onboard" style="width: 32px; height: 32px; margin: 0 auto 4px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: <?php echo $isOnboarded ? '#10b981' : '#e5e7eb'; ?>; color: <?php echo $isOnboarded ? '#fff' : '#6b7280'; ?>; font-weight: 600; font-size: 14px;">
                        <?php if ($isOnboarded): ?>
                            <span class="dashicons dashicons-yes" style="font-size: 20px;"></span>
                        <?php else: ?>
                            <?php echo $channel === 'direct' ? '4' : '3'; ?>
                        <?php endif; ?>
                    </div>
                    <div data-step-label="onboard" style="font-size: 10px; font-weight: 600; color: <?php echo $isOnboarded ? '#10b981' : '#6b7280'; ?>;">
                        Onboard
                    </div>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div style="height: 4px; background: #e5e7eb; border-radius: 2px; overflow: hidden;">
                <div data-progress-bar style="height: 100%; background: linear-gradient(90deg, #10b981 0%, #059669 100%); width: <?php echo esc_attr($progress); ?>%; transition: width 0.3s ease;"></div>
            </div>
        </div>
        
        <!-- Meta Information -->
        <?php if (!empty($flowMeta)): ?>
        <div>
            <div style="font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Metadata</div>
            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px; font-family: 'Courier New', monospace; font-size: 11px; color: #374151; max-height: 150px; overflow-y: auto;">
                <?php foreach ($flowMeta as $key => $value): ?>
                    <div style="margin: 3px 0; word-break: break-word;">
                        <span style="color: #2563eb; font-weight: 600;"><?php echo esc_html($key); ?>:</span>
                        <span style="color: #6b7280;"><?php echo esc_html(is_array($value) || is_object($value) ? json_encode($value) : $value); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
