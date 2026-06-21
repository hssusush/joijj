import { initializeApp, applicationDefault } from 'firebase-admin/app';
import { getFirestore } from 'firebase-admin/firestore';
import config from './firebase-applet-config.json' with { type: 'json' };

try {
  console.log("GOOGLE_APPLICATION_CREDENTIALS", process.env.GOOGLE_APPLICATION_CREDENTIALS);
  initializeApp({
    credential: applicationDefault(),
    projectId: config.projectId,
  });
  console.log("Firebase Admin initialized successfully");
  const db = getFirestore();
  db.settings({ databaseId: config.firestoreDatabaseId });
  await db.collection('test').doc('test').get();
  console.log("Firestore object created");
} catch (error) {
  console.error("Firebase Admin Error:", error);
}
