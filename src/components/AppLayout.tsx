import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router';
import { auth, db } from '../firebase';
import { signOut } from 'firebase/auth';
import { doc, onSnapshot } from 'firebase/firestore';
import { 
  Shield, 
  LogOut, 
  LayoutDashboard, 
  User as UserIcon,
  KeyRound,
  ShieldCheck
} from 'lucide-react';

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const navigate = useNavigate();
  const [userProfile, setUserProfile] = useState<any>(null);

  useEffect(() => {
    const uid = auth.currentUser?.uid;
    if (!uid) return;
    const unsub = onSnapshot(doc(db, 'users', uid), (docSnap) => {
      if (docSnap.exists()) setUserProfile(docSnap.data());
    });
    return unsub;
  }, []);

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-[#0A0A0A] text-neutral-800 dark:text-neutral-300 font-sans flex">
      {/* Sidebar */}
      <aside className="w-64 border-r border-neutral-200 dark:border-neutral-800/60 bg-white/80 dark:bg-[#0A0A0A]/80 flex-col hidden md:flex h-screen sticky top-0">
        <div className="p-6 flex items-center space-x-3 mb-6">
           <div className="w-8 h-8 bg-gradient-to-tr from-orange-600 to-orange-400 rounded-md flex items-center justify-center shadow-lg shadow-orange-500/20">
             <Shield className="w-4 h-4 text-white" />
           </div>
           <span className="text-black dark:text-white font-semibold tracking-tight">License Key Manager</span>
        </div>
        <nav className="flex-1 px-4 space-y-2">
          <button onClick={() => navigate('/dashboard')} className="w-full flex items-center space-x-3 px-3 py-2 text-neutral-600 dark:text-neutral-400 hover:text-black dark:hover:text-white hover:bg-gray-100 dark:hover:bg-neutral-900 rounded-lg transition-colors">
            <LayoutDashboard className="w-4 h-4" />
            <span>Dashboard</span>
          </button>
          <button onClick={() => navigate('/keys')} className="w-full flex items-center space-x-3 px-3 py-2 text-neutral-600 dark:text-neutral-400 hover:text-black dark:hover:text-white hover:bg-gray-100 dark:hover:bg-neutral-900 rounded-lg transition-colors">
            <KeyRound className="w-4 h-4" />
            <span>API Keys</span>
          </button>
          <button onClick={() => navigate('/profile')} className="w-full flex items-center space-x-3 px-3 py-2 text-neutral-600 dark:text-neutral-400 hover:text-black dark:hover:text-white hover:bg-gray-100 dark:hover:bg-neutral-900 rounded-lg transition-colors">
            <UserIcon className="w-4 h-4" />
            <span>Profile</span>
          </button>
          
          {userProfile?.role === 'admin' && (
            <button onClick={() => navigate('/admin')} className="w-full flex items-center space-x-3 px-3 py-2 text-orange-600 dark:text-orange-500 hover:text-orange-700 dark:hover:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-500/10 rounded-lg transition-colors">
              <ShieldCheck className="w-4 h-4" />
              <span>Admin Gateway</span>
            </button>
          )}
        </nav>
        <div className="p-4 border-t border-neutral-200 dark:border-neutral-800/60 flex flex-col space-y-3">
           <div className="flex items-center space-x-3 px-2">
             {auth.currentUser?.photoURL ? (
               <img src={auth.currentUser.photoURL} alt="Profile" className="w-8 h-8 rounded-full border border-neutral-200 dark:border-neutral-700" />
             ) : (
               <div className="w-8 h-8 bg-gray-200 dark:bg-neutral-800 rounded-full flex items-center justify-center">
                 <UserIcon className="w-4 h-4 text-neutral-500" />
               </div>
             )}
             <div className="flex-1 min-w-0">
               <div className="text-sm font-medium text-black dark:text-white truncate">{auth.currentUser?.displayName || 'User'}</div>
               <div className="text-xs text-neutral-500 truncate">{auth.currentUser?.email}</div>
             </div>
           </div>
           <button onClick={() => signOut(auth)} className="w-full flex items-center space-x-3 px-3 py-2 text-neutral-600 dark:text-neutral-400 hover:text-black dark:hover:text-white hover:bg-gray-100 dark:hover:bg-neutral-900 rounded-lg transition-colors">
             <LogOut className="w-4 h-4" />
             <span>Sign Out</span>
           </button>
        </div>
      </aside>
      <main className="flex-1 flex flex-col min-h-screen relative max-w-full overflow-hidden">
         <div className="md:hidden flex items-center justify-between p-4 border-b border-neutral-200 dark:border-neutral-800 bg-white dark:bg-[#0A0A0A] sticky top-0 z-50">
           <div className="flex items-center space-x-2">
             <Shield className="w-5 h-5 text-orange-500" />
             <span className="text-black dark:text-white font-medium">License Key Manager</span>
           </div>
           <div className="flex space-x-4">
               <button onClick={() => navigate('/dashboard')}><LayoutDashboard className="w-5 h-5 text-neutral-600 dark:text-neutral-400" /></button>
               <button onClick={() => navigate('/keys')}><KeyRound className="w-5 h-5 text-neutral-600 dark:text-neutral-400" /></button>
               <button onClick={() => navigate('/profile')}><UserIcon className="w-5 h-5 text-neutral-600 dark:text-neutral-400" /></button>
               <button onClick={() => signOut(auth)}><LogOut className="w-5 h-5 text-neutral-600 dark:text-neutral-400" /></button>
           </div>
         </div>
         <div className="flex-1 w-full relative overflow-y-auto">
           {children}
         </div>
       </main>
    </div>
  );
}
