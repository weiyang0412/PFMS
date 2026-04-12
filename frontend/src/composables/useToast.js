import { ref } from 'vue';

const visible = ref(false);
const message = ref('');
const tone = ref('success');
let timer = null;

const show = (nextMessage, nextTone = 'success', duration = 2600) => {
  message.value = nextMessage;
  tone.value = nextTone;
  visible.value = true;
  if (timer) clearTimeout(timer);
  timer = setTimeout(() => {
    visible.value = false;
  }, duration);
};

export const useToast = () => ({
  visible,
  message,
  tone,
  show,
});
