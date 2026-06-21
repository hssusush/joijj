import { initializeApp } from 'firebase/app';
import { getAuth, createUserWithEmailAndPassword } from 'firebase/auth';
import config from './firebase-applet-config.json' with { type: 'json' };

const app = initializeApp(config);
const auth = getAuth(app);

async function setup() {
  try {
    const userCredential = await createUserWithEmailAndPassword(auth, 'stripe_bot@openclaw.ai', 'stripe_bot_super_secret');
    console.log("Created Webhook Bot:", userCredential.user.uid);
  } catch (error) {
    console.log("Bot already created or error:", error);
  }
  process.exit();
}

setup();
