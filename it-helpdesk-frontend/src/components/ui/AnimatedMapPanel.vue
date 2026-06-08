<template>
  <div class="relative w-full h-full overflow-hidden select-none">

    <!-- Full-panel image -->
    <img
      src="/SEGView.png"
      alt="SEG Solar"
      class="absolute inset-0 w-full h-full object-cover"
    />

    <!-- Vignette: darkens edges except the right side (right is handled by LoginView gradient) -->
    <div class="vignette" />

    <!-- ── Galaxy circle — centered ────────────────────────────── -->
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
      <div class="galaxy">

        <!-- Outermost slow ring -->
        <div class="ring-track ring-6">
          <span class="dot dot-blue" />
        </div>

        <!-- Fifth ring — reversed -->
        <div class="ring-track ring-5 ring-rev">
          <span class="dot dot-white" />
          <span class="dot dot-white dot-half" />
        </div>

        <!-- Fourth ring -->
        <div class="ring-track ring-4">
          <span class="dot dot-teal" />
          <span class="dot dot-teal dot-third" />
          <span class="dot dot-teal dot-twothird" />
        </div>

        <!-- Third ring — reversed, faster -->
        <div class="ring-track ring-3 ring-rev">
          <span class="dot dot-yellow" />
          <span class="dot dot-yellow dot-half" />
        </div>

        <!-- Second ring -->
        <div class="ring-track ring-2">
          <span class="dot dot-red" />
        </div>

        <!-- Inner ring — reversed, fastest -->
        <div class="ring-track ring-1 ring-rev">
          <span class="dot dot-white" />
        </div>

        <!-- Pulsing core glow layers -->
        <div class="core-halo core-halo-3" />
        <div class="core-halo core-halo-2" />
        <div class="core-halo core-halo-1" />
        <div class="core" />

      </div>
    </div>

    <!-- Tagline — anchored left so it stays in the visible image area -->
    <div class="absolute bottom-7 left-8 space-y-0.5">
      <p class="text-sm font-semibold tracking-wide text-white/80 drop-shadow">IT Ticketing System</p>
      <p class="text-xs tracking-wider text-white/50 drop-shadow">SEG Solar Manufaktur Indonesia</p>
    </div>

  </div>
</template>

<script setup lang="ts">
// No reactive state needed — pure CSS animation
</script>

<style scoped>
/* ── Vignette — only left/top/bottom edges; right is handled by LoginView gradient ── */
.vignette {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to right,
    rgba(0, 0, 0, 0.18) 0%,
    transparent        20%,
    transparent        75%,
    rgba(255,255,255,0) 100%
  );
  pointer-events: none;
}

/* ── Galaxy wrapper ────────────────────────────────────────────── */
.galaxy {
  position: relative;
  width: 0;
  height: 0;
}

/* ── Ring tracks ────────────────────────────────────────────────
   Each track is a zero-dimension div rotated over time.
   A dot sits at (radius, 0) so it orbits as the track rotates.
   ─────────────────────────────────────────────────────────────── */
.ring-track {
  position: absolute;
  top: 0; left: 0;
  width: 0; height: 0;
  border-radius: 50%;
}

/* Visible ring circle drawn as outline via box-shadow */
.ring-track::before {
  content: '';
  position: absolute;
  border-radius: 50%;
  border: 1px solid rgba(255, 255, 255, 0.12);
  transform: translate(-50%, -50%);
  pointer-events: none;
}

/* Ring sizes */
.ring-1::before { width:  56px; height:  56px; }
.ring-2::before { width: 104px; height: 104px; }
.ring-3::before { width: 162px; height: 162px; }
.ring-4::before { width: 226px; height: 226px; }
.ring-5::before { width: 298px; height: 298px; }
.ring-6::before { width: 380px; height: 380px; }

/* Rotation animations — each ring at a unique speed */
.ring-1 { animation: orbit 4.7s  linear infinite; }
.ring-2 { animation: orbit 8.3s  linear infinite; }
.ring-3 { animation: orbit 13.1s linear infinite; }
.ring-4 { animation: orbit 19.7s linear infinite; }
.ring-5 { animation: orbit 27.3s linear infinite; }
.ring-6 { animation: orbit 38.9s linear infinite; }

/* Reversed rings */
.ring-rev { animation-direction: reverse; }

@keyframes orbit {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}

/* ── Dots — positioned along each ring radius ──────────────────── */
.dot {
  position: absolute;
  border-radius: 50%;
  /* counter-rotate so the dot face stays upright */
  top: 0; left: 0;
}

/* Dot radii (half of ring diameter / 2) */
.ring-1 .dot { width: 5px; height: 5px; margin: -2.5px; transform: translateX(28px)  rotate(0deg); }
.ring-2 .dot { width: 6px; height: 6px; margin: -3px;   transform: translateX(52px)  rotate(0deg); }
.ring-3 .dot { width: 7px; height: 7px; margin: -3.5px; transform: translateX(81px)  rotate(0deg); }
.ring-4 .dot { width: 7px; height: 7px; margin: -3.5px; transform: translateX(113px) rotate(0deg); }
.ring-5 .dot { width: 6px; height: 6px; margin: -3px;   transform: translateX(149px) rotate(0deg); }
.ring-6 .dot { width: 6px; height: 6px; margin: -3px;   transform: translateX(190px) rotate(0deg); }

/* Offset dots at fractions of the ring circumference */
.dot-half     { transform: rotate(180deg) translateX(var(--r,  28px)); }
.dot-third    { transform: rotate(120deg) translateX(var(--r,  28px)); }
.dot-twothird { transform: rotate(240deg) translateX(var(--r,  28px)); }

/* Override radius var per ring */
.ring-3 .dot-half     { transform: rotate(180deg) translateX(81px);  }
.ring-4 .dot-third    { transform: rotate(120deg) translateX(113px); }
.ring-4 .dot-twothird { transform: rotate(240deg) translateX(113px); }
.ring-5 .dot-half     { transform: rotate(180deg) translateX(149px); }

/* Dot colours + glow */
.dot-white  { background: rgba(255,255,255,0.90); box-shadow: 0 0 6px 2px rgba(255,255,255,0.55); }
.dot-blue   { background: #60a5fa; box-shadow: 0 0 7px 3px rgba(96,165,250,0.65); }
.dot-teal   { background: #2dd4bf; box-shadow: 0 0 7px 3px rgba(45,212,191,0.65); }
.dot-yellow { background: #fbbf24; box-shadow: 0 0 7px 3px rgba(251,191,36,0.65); }
.dot-red    { background: #f87171; box-shadow: 0 0 7px 3px rgba(248,113,113,0.65); }

/* ── Core ───────────────────────────────────────────────────────── */
.core {
  position: absolute;
  width: 14px; height: 14px;
  margin: -7px;
  border-radius: 50%;
  background: radial-gradient(circle, #ffffff 30%, #93c5fd 70%, #6366f1 100%);
  box-shadow: 0 0 14px 5px rgba(147,197,253,0.80), 0 0 28px 10px rgba(99,102,241,0.40);
  animation: corePulse 3.1s ease-in-out infinite;
}

/* Halo rings expanding outward from core */
.core-halo {
  position: absolute;
  border-radius: 50%;
  border: 1.5px solid rgba(147, 197, 253, 0.35);
  animation: haloPulse 3.1s ease-out infinite;
}
.core-halo-1 { width: 28px;  height: 28px;  margin: -14px; animation-delay: 0s; }
.core-halo-2 { width: 44px;  height: 44px;  margin: -22px; animation-delay: 0.6s; }
.core-halo-3 { width: 64px;  height: 64px;  margin: -32px; animation-delay: 1.2s; }

@keyframes corePulse {
  0%,100% { transform: scale(1);    box-shadow: 0 0 14px 5px rgba(147,197,253,0.80), 0 0 28px 10px rgba(99,102,241,0.40); }
  50%      { transform: scale(1.2); box-shadow: 0 0 20px 8px rgba(147,197,253,0.90), 0 0 40px 16px rgba(99,102,241,0.55); }
}

@keyframes haloPulse {
  0%   { transform: scale(1);   opacity: 0.7; }
  60%  { transform: scale(1.5); opacity: 0.2; }
  100% { transform: scale(2);   opacity: 0; }
}
</style>
