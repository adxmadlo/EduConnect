/**
 * EduConnect — Firebase Firestore Init
 * Remplacer les valeurs ci-dessous par votre vraie config Firebase
 * Obtenir depuis : Firebase Console > Project Settings > Your Apps > SDK Config
 */

// ⚠️ REMPLACER CES VALEURS PAR VOTRE VRAIE CONFIG FIREBASE
const FIREBASE_CONFIG = {
    apiKey:            "VOTRE_API_KEY",
    authDomain:        "VOTRE_PROJECT.firebaseapp.com",
    projectId:         "VOTRE_PROJECT_ID",
    storageBucket:     "VOTRE_PROJECT.appspot.com",
    messagingSenderId: "VOTRE_SENDER_ID",
    appId:             "VOTRE_APP_ID"
};

// Initialiser Firebase
let db = null;
let firebaseApp = null;

function initFirebase() {
    try {
        if (typeof firebase === 'undefined') {
            console.warn('[EduConnect] Firebase SDK non chargé');
            return false;
        }
        if (!firebase.apps.length) {
            firebaseApp = firebase.initializeApp(FIREBASE_CONFIG);
        } else {
            firebaseApp = firebase.apps[0];
        }
        db = firebase.firestore();
        db.settings({ experimentalForceLongPolling: true }); // Fix pour certains navigateurs
        console.log('[EduConnect] Firebase initialisé ✓');
        return true;
    } catch (e) {
        console.error('[EduConnect] Erreur Firebase init:', e);
        return false;
    }
}

/**
 * Envoyer un message dans une conversation
 * @param {string} convId — ID de la conversation (ex: "user_1_user_5")
 * @param {string} body   — Corps du message
 * @param {object} sender — { uid, nom, initiales, role }
 */
async function sendMessage(convId, body, sender) {
    if (!db) { console.error('Firebase non initialisé'); return; }
    try {
        const msgRef = db.collection('conversations').doc(convId).collection('messages');
        await msgRef.add({
            expediteurId: sender.uid,
            expediteurNom: sender.nom,
            corps: body,
            createdAt: firebase.firestore.FieldValue.serverTimestamp(),
            luPar: [sender.uid]
        });
        // Mettre à jour la conversation
        await db.collection('conversations').doc(convId).set({
            dernierMessage: body,
            updatedAt: firebase.firestore.FieldValue.serverTimestamp(),
            membres: firebase.firestore.FieldValue.arrayUnion(sender.uid)
        }, { merge: true });
        return true;
    } catch (e) {
        console.error('[EduConnect] Erreur sendMessage:', e);
        return false;
    }
}

/**
 * Écouter les messages d'une conversation (temps réel)
 * @param {string}   convId   — ID de la conversation
 * @param {function} callback — fn(messages[])
 * @returns {function} unsubscribe — appeler pour arrêter l'écoute
 */
function listenMessages(convId, callback) {
    if (!db) return () => {};
    return db.collection('conversations').doc(convId)
        .collection('messages')
        .orderBy('createdAt', 'asc')
        .onSnapshot(snap => {
            const msgs = snap.docs.map(d => ({ id: d.id, ...d.data() }));
            callback(msgs);
        }, err => console.error('[EduConnect] listenMessages error:', err));
}

/**
 * Marquer les messages comme lus
 * @param {string} convId — ID de la conversation
 * @param {number} userId — ID de l'utilisateur courant
 */
async function markAsRead(convId, userId) {
    if (!db) return;
    try {
        const msgs = await db.collection('conversations').doc(convId)
            .collection('messages')
            .where('luPar', 'array-contains', userId)
            .limit(1).get();
        // Marquer les non-lus
        const batch = db.batch();
        const unread = await db.collection('conversations').doc(convId)
            .collection('messages')
            .orderBy('createdAt', 'desc').limit(20).get();
        unread.docs.forEach(doc => {
            if (!doc.data().luPar?.includes(userId)) {
                batch.update(doc.ref, {
                    luPar: firebase.firestore.FieldValue.arrayUnion(userId)
                });
            }
        });
        await batch.commit();
    } catch(e) {
        console.error('[EduConnect] markAsRead error:', e);
    }
}

/**
 * Générer un ID de conversation déterministe entre 2 utilisateurs
 */
function getConvId(uid1, uid2) {
    return [uid1, uid2].sort().join('_');
}

// Auto-init au chargement
document.addEventListener('DOMContentLoaded', initFirebase);
