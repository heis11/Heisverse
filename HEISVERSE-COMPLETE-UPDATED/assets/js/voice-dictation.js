/**
 * HEISVERSE — Voice Dictation Engine (Speech-to-Text)
 * Uses the Web Speech Recognition API with permission handling, visual listening state,
 * and review-before-submit capability.
 */

'use strict';

const VoiceDictation = {
  recognition: null,
  isListening: false,
  activeTargetInput: null,
  statusCallback: null,

  init() {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
      console.info('[HEISVERSE Voice] Speech recognition not supported in this browser.');
      return false;
    }

    this.recognition = new SpeechRecognition();
    this.recognition.continuous = false;
    this.recognition.interimResults = true;
    this.recognition.lang = document.documentElement.lang || 'en-US';

    this.recognition.onstart = () => {
      this.isListening = true;
      this.updateUI(true);
      if (typeof showToast === 'function') {
        showToast('🎙️ Listening... Speak into your microphone.');
      }
    };

    this.recognition.onresult = (event) => {
      let transcript = '';
      for (let i = event.resultIndex; i < event.results.length; ++i) {
        transcript += event.results[i][0].transcript;
      }

      if (this.activeTargetInput) {
        this.activeTargetInput.value = transcript;
        // Trigger live search or input event
        this.activeTargetInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
    };

    this.recognition.onerror = (event) => {
      console.warn('[HEISVERSE Voice Error]', event.error);
      this.isListening = false;
      this.updateUI(false);
      let msg = 'Voice recognition error.';
      if (event.error === 'not-allowed') {
        msg = 'Microphone permission denied. Please allow microphone access in your browser settings.';
      } else if (event.error === 'no-speech') {
        msg = 'No speech detected. Please try again.';
      }
      if (typeof showToast === 'function') showToast(msg, 'warning');
    };

    this.recognition.onend = () => {
      this.isListening = false;
      this.updateUI(false);
    };

    return true;
  },

  toggle(targetInputId, micButtonEl) {
    if (!this.recognition && !this.init()) {
      if (typeof showToast === 'function') {
        showToast('Voice dictation is not supported by your current browser. Try Chrome or Edge.', 'warning');
      }
      return;
    }

    this.activeTargetInput = document.getElementById(targetInputId);
    this.activeButton = micButtonEl;

    if (this.isListening) {
      this.recognition.stop();
    } else {
      try {
        this.recognition.start();
      } catch (err) {
        console.warn(err);
      }
    }
  },

  updateUI(listening) {
    if (this.activeButton) {
      if (listening) {
        this.activeButton.classList.add('voice-listening');
        this.activeButton.innerHTML = '🔴 Listening...';
      } else {
        this.activeButton.classList.remove('voice-listening');
        this.activeButton.innerHTML = '🎙️';
      }
    }
  }
};

document.addEventListener('DOMContentLoaded', () => {
  VoiceDictation.init();
});
