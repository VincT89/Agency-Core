<?php if (isset($component)) { $__componentOriginal69dc84650370d1d4dc1b42d016d7226b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal69dc84650370d1d4dc1b42d016d7226b = $attributes; } ?>
<?php $component = App\View\Components\GuestLayout::resolve(['title' => 'Accedi — Sodano Consulting'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('guest-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\GuestLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

  <div class="login-shell">

    
    <div class="login-left">
      <canvas id="bg-canvas" class="login-canvas"></canvas>
      <div class="login-left-top">
        <div class="login-logo">
          <img src="<?php echo e(asset('images/logo.png')); ?>" alt="Sodano Consulting">
          <span class="login-logo-name">Sodano Consulting</span>
        </div>
        <div class="login-eyebrow">Gestionale interno</div>
        <div class="login-title">
          Strategia,<br>Innovazione,<br>
          <strong>Performance.</strong>
        </div>
      </div>

      <div class="login-left-bottom">
        <div class="login-stat-strip">
          <div class="login-stat-cell">
            <div class="login-stat-val"><em>10</em>+</div>
            <div class="login-stat-lbl">Anni</div>
          </div>
          <div class="login-stat-cell">
            <div class="login-stat-val">Dev</div>
            <div class="login-stat-lbl">Developer</div>
          </div>
          <div class="login-stat-cell">
            <div class="login-stat-val">Mkt</div>
            <div class="login-stat-lbl">Marketing</div>
          </div>
          <div class="login-stat-cell">
            <div class="login-stat-val">Sys</div>
            <div class="login-stat-lbl">System</div>
          </div>
          <div class="login-stat-cell">
            <div class="login-stat-val">Sht</div>
            <div class="login-stat-lbl">Shooting</div>
          </div>
        </div>
        <div class="login-copy">© 2015–<?php echo e(date('Y')); ?> Sodano Consulting S.r.l.</div>
      </div>
    </div>

    
    <div class="login-right">

      <div class="login-form-top">
        <div class="login-form-eyebrow">Bentornato</div>
        <div class="login-form-title">Accedi al tuo<br>account</div>
      </div>

      
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?>
        <div class="flash flash-error login-flash">
          <?php echo e(session('status')); ?>

        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <form method="POST" action="<?php echo e(route('login')); ?>">
        <?php echo csrf_field(); ?>

        
        <div class="form-g login-form-g">
          <div class="form-lbl">Email aziendale</div>
          <input
            type="email"
            name="email"
            class="form-in <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
            value="<?php echo e(old('email')); ?>"
            placeholder="nome@sodanoconsulting.it"
            required
            autofocus
            autocomplete="email"
          >
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <div class="invalid-feedback"><?php echo e($message); ?></div>
          <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="form-g login-form-g last">
          <div class="form-lbl">Password</div>
          <input
            type="password"
            name="password"
            class="form-in <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
            placeholder="••••••••"
            required
            autocomplete="current-password"
          >
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <div class="invalid-feedback"><?php echo e($message); ?></div>
          <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="login-remember">
          <input type="checkbox" name="remember" id="remember" class="login-checkbox">
          <label for="remember" class="login-checkbox-lbl">
            Ricordami
          </label>
        </div>

        <div class="login-form-footer">
          <div class="login-form-hint">
            Problemi di accesso?<br>Contatta l'amministratore.
          </div>
          <button type="submit" class="btn btn-p">Entra →</button>
        </div>
      </form>

    </div>
  </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal69dc84650370d1d4dc1b42d016d7226b)): ?>
<?php $attributes = $__attributesOriginal69dc84650370d1d4dc1b42d016d7226b; ?>
<?php unset($__attributesOriginal69dc84650370d1d4dc1b42d016d7226b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal69dc84650370d1d4dc1b42d016d7226b)): ?>
<?php $component = $__componentOriginal69dc84650370d1d4dc1b42d016d7226b; ?>
<?php unset($__componentOriginal69dc84650370d1d4dc1b42d016d7226b); ?>
<?php endif; ?>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\GESTIONALE\Agency-core\resources\views/auth/login.blade.php ENDPATH**/ ?>