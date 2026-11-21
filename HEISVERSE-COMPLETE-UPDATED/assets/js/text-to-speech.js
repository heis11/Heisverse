/**
 * HEISVERSE — Text-to-Speech Engine (Read-Aloud)
 * Uses Web SpeechSynthesis with speed, volume, and voice selection controls.
 */

'use strict';

const TextToSpeech = {
  synth: window.speechSynthesis || null,
  voices: [],
  currentUtterance: null,
  isPlaying: false,
  rate: parseFloat(localStorage.getItem('heisverse_tts_rate') || '1.0'),
  volume: parseFloat(localStorage.getItem('heisverse_tts_volume') || '1.0'),
  voiceIndex: parseInt(localStorage.getItem('heisverse_tts_voice') || '0', 10),

  init() {
    if (!this.synth) {
      console.info('[HEISVERSE TTS] Speech synthesis not supported in this browser.');
      return;
    }

    const loadVoices = () => {
      this.voices = this.synth.getVoices();
    };

    loadVoices();
    if (this.synth.onvoiceschanged !== undefined) {
      this.synth.onvoiceschanged = loadVoices;
    }
  },

  speak(text, elementToHighlight = null) {
    if (!this.synth) {
      if (typeof showToast === 'function') {
        showToast('Text-to-speech is not supported in this browser.', 'warning');
      }
      return;
    }

    if (this.synth.speaking) {
      this.stop();
      if (this.currentText === text) return;
    }

    this.currentText = text;
    this.currentUtterance = new SpeechSynthesisUtterance(text);
    this.currentUtterance.rate = this.rate;
    this.currentUtterance.volume = this.volume;

    if (this.voices.length > 0 && this.voices[this.voiceIndex]) {
      this.currentUtterance.voice = this.voices[this.voiceIndex];
    }

    if (elementToHighlight) {
      elementToHighlight.classList.add('tts-highlight');
    }

    this.currentUtterance.onend = () => {
      this.isPlaying = false;
      if (elementToHighlight) {
        elementToHighlight.classList.remove('tts-highlight');
      }
    };

    this.currentUtterance.onerror = () => {
      this.isPlaying = false;
      if (elementToHighlight) {
        elementToHighlight.classList.remove('tts-highlight');
      }
    };

    this.isPlaying = true;
    this.synth.speak(this.currentUtterance);
  },

  stop() {
    if (this.synth) {
      this.synth.cancel();
      this.isPlaying = false;
      document.querySelectorAll('.tts-highlight').forEach(el => el.classList.remove('tts-highlight'));
    }
  },

  setRate(newRate) {
    this.rate = parseFloat(newRate);
    localStorage.setItem('heisverse_tts_rate', this.rate.toString());
  },

  setVolume(newVolume) {
    this.volume = parseFloat(newVolume);
    localStorage.setItem('heisverse_tts_volume', this.volume.toString());
  }
};

document.addEventListener('DOMContentLoaded', () => {
  TextToSpeech.init();
});
