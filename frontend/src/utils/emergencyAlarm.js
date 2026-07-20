// Synthesized siren (no static audio file needed) so an emergency alert is
// unmistakably audible, not just another silent badge/toast. Sawtooth wave
// (harsher than sine) with a low-frequency oscillator sweeping the pitch up
// and down mimics a classic alarm/siren wail rather than a soft notification
// chime, so it can't be confused with a normal "ding".

let audioContext = null;
let activeSiren = null;

function getContext() {
  if (!audioContext) {
    const Ctor = window.AudioContext || window.webkitAudioContext;
    audioContext = new Ctor();
  }
  return audioContext;
}

// Browsers suspend a freshly created AudioContext until a real user gesture
// occurs. Call this from any early click/keydown so the context is already
// running by the time an emergency alert actually needs to play.
export function unlockEmergencyAudio() {
  const ctx = getContext();
  if (ctx.state === 'suspended') {
    ctx.resume().catch(() => {});
  }
}

export function startEmergencyAlarm() {
  if (activeSiren) return;
  const ctx = getContext();
  if (ctx.state === 'suspended') {
    ctx.resume().catch(() => {});
  }

  const oscillator = ctx.createOscillator();
  oscillator.type = 'sawtooth';
  oscillator.frequency.value = 700;

  const sweep = ctx.createOscillator();
  sweep.type = 'sine';
  sweep.frequency.value = 0.6; // wails ~once every ~1.7s
  const sweepDepth = ctx.createGain();
  sweepDepth.gain.value = 300; // +/- 300Hz sweep around the base frequency
  sweep.connect(sweepDepth).connect(oscillator.frequency);

  const volume = ctx.createGain();
  volume.gain.setValueAtTime(0.0001, ctx.currentTime);
  volume.gain.exponentialRampToValueAtTime(0.35, ctx.currentTime + 0.05);

  oscillator.connect(volume).connect(ctx.destination);
  oscillator.start();
  sweep.start();

  activeSiren = { oscillator, sweep, volume };
}

export function stopEmergencyAlarm() {
  if (!activeSiren) return;
  const { oscillator, sweep, volume } = activeSiren;
  const ctx = getContext();
  const now = ctx.currentTime;
  volume.gain.cancelScheduledValues(now);
  volume.gain.setValueAtTime(volume.gain.value, now);
  volume.gain.exponentialRampToValueAtTime(0.0001, now + 0.15);
  oscillator.stop(now + 0.2);
  sweep.stop(now + 0.2);
  activeSiren = null;
}
