import { useState, useEffect } from 'react';
import { auth, db } from '../firebase';
import { doc, onSnapshot } from 'firebase/firestore';
import { useTheme } from '../components/ThemeContext';
import { Sun, Moon, User as UserIcon } from 'lucide-react';

export default function Profile() {
  const [userProfile, setUserProfile] = useState<any>(null);
  const { theme, toggleTheme } = useTheme();
  
  useEffect(() => {
    const uid = auth.currentUser?.uid;
    if (!uid) return;
    const unsub = onSnapshot(doc(db, 'users', uid), (docSnap) => {
      if (docSnap.exists()) setUserProfile(docSnap.data());
    });
    return unsub;
  }, []);

  return (
    <div className="max-w-4xl mx-auto py-12 px-6">
      <div className="flex justify-between items-center mb-8">
        <h1 className="text-3xl font-bold text-black dark:text-white tracking-tight">User Profile</h1>
        <button 
          onClick={toggleTheme}
          className="flex items-center space-x-2 px-4 py-2 bg-gray-200 dark:bg-neutral-800 rounded-lg text-sm font-medium text-black dark:text-white transition-colors"
        >
          {theme === 'dark' ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
          <span>{theme === 'dark' ? 'Light Mode' : 'Dark Mode'}</span>
        </button>
      </div>
      <div className="bg-gray-50 dark:bg-[#111111] border border-neutral-200 dark:border-neutral-800 rounded-2xl p-6">
        <div className="flex items-center space-x-4 mb-6">
          <div className="w-16 h-16 bg-gray-200 dark:bg-neutral-800 rounded-full flex items-center justify-center overflow-hidden border-2 border-neutral-200 dark:border-neutral-800">
            {auth.currentUser?.photoURL ? (
               <img src={auth.currentUser.photoURL} alt="Profile" className="w-full h-full object-cover" />
            ) : (
               <UserIcon className="w-8 h-8 text-neutral-500" />
            )}
          </div>
          <div>
            <div className="text-xl font-medium text-black dark:text-white">{auth.currentUser?.displayName || 'User'}</div>
            <div className="text-neutral-600 dark:text-neutral-400">{auth.currentUser?.email}</div>
          </div>
        </div>
        <div className="grid grid-cols-2 gap-4 border-t border-neutral-200 dark:border-neutral-800 pt-6 mt-6">
          <div>
            <div className="text-sm text-neutral-500 mb-1">Current Plan</div>
            <div className="text-black dark:text-white capitalize px-2 py-1 bg-gray-200 dark:bg-neutral-800 rounded-md inline-block text-sm">{userProfile?.planId || 'Starter'}</div>
          </div>
          <div>
            <div className="text-sm text-neutral-500 mb-1">Account Status</div>
            <div className="text-black dark:text-white capitalize px-2 py-1 bg-green-500/10 text-green-500 rounded-md inline-block text-sm">{userProfile?.status || 'Active'}</div>
          </div>
          <div>
            <div className="text-sm text-neutral-500 mb-1">Member Since</div>
            <div className="text-black dark:text-white">{new Date(userProfile?.createdAt || Date.now()).toLocaleDateString()}</div>
          </div>
        </div>
      </div>
    </div>
  );
}
