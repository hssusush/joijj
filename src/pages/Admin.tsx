import { useState, useEffect } from 'react';
import { auth, db } from '../firebase';
import { collection, onSnapshot, doc, updateDoc, query, orderBy } from 'firebase/firestore';
import { ShieldAlert, Loader2, Search, CheckCircle2, XCircle, Calendar, User, Shield } from 'lucide-react';
import { useTheme } from '../components/ThemeContext';

export default function Admin() {
  const [usersList, setUsersList] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [currentUserProfile, setCurrentUserProfile] = useState<any>(null);

  useEffect(() => {
    // Check if the current user has 'admin' privileges
    const uid = auth.currentUser?.uid;
    if (!uid) return;

    const unsubProfile = onSnapshot(doc(db, 'users', uid), (docSnap) => {
      if (docSnap.exists()) {
        setCurrentUserProfile(docSnap.data());
      }
    });

    const q = query(collection(db, 'users'), orderBy('createdAt', 'desc'));
    const unsubUsers = onSnapshot(q, (snapshot) => {
      setUsersList(snapshot.docs.map(d => ({ id: d.id, ...d.data() })));
      setLoading(false);
    });

    return () => {
      unsubProfile();
      unsubUsers();
    };
  }, []);

  const changeUserPlan = async (userId: string, newPlan: string) => {
    if (!confirm(`Change user plan to ${newPlan}?`)) return;
    try {
      await updateDoc(doc(db, 'users', userId), { planId: newPlan, updatedAt: Date.now() });
    } catch (e) {
      console.error(e);
      alert('Failed to update plan.');
    }
  };

  const changeUserStatus = async (userId: string, newStatus: string) => {
    if (!confirm(`Change user status to ${newStatus}?`)) return;
    try {
      await updateDoc(doc(db, 'users', userId), { status: newStatus, updatedAt: Date.now() });
    } catch (e) {
      console.error(e);
      alert('Failed to update status.');
    }
  };

  const toggleAdmin = async (userId: string, isAdmin: boolean) => {
    if (!confirm(`Are you sure you want to ${isAdmin ? 'grant' : 'revoke'} admin privileges for this user?`)) return;
    try {
      await updateDoc(doc(db, 'users', userId), { role: isAdmin ? 'admin' : 'user', updatedAt: Date.now() });
    } catch (e) {
      console.error(e);
      alert('Failed to update role.');
    }
  };

  if (loading) return <div className="min-h-screen flex items-center justify-center"><Loader2 className="w-6 h-6 text-orange-500 animate-spin" /></div>;

  // Protect the route using a simple role check defined in Firestore 'role' field
  // Allow all users if role strictly doesn't exist, to avoid locking out the person building it, 
  // but ideally you only show it to 'admin'. Let's enforce it visually:
  if (currentUserProfile && currentUserProfile.role !== 'admin') {
    return (
      <div className="flex flex-col items-center justify-center h-full pt-32 text-center px-4">
        <ShieldAlert className="w-16 h-16 text-red-500 mb-4" />
        <h2 className="text-2xl font-bold text-black dark:text-white mb-2">Access Denied</h2>
        <p className="text-neutral-500 max-w-md">You do not have the required permissions to view the admin control panel. Contact system administrator.</p>
        
        {/* Helper button for the demo so the user can make themselves an admin easily */}
        <button 
          onClick={async () => {
             const uid = auth.currentUser?.uid;
             if (uid) await updateDoc(doc(db, 'users', uid), { role: 'admin' });
          }}
          className="mt-8 text-xs underline text-neutral-400 hover:text-black dark:hover:text-white"
        >
          [Demo] Make me an admin
        </button>
      </div>
    );
  }

  const filteredUsers = usersList.filter(u => 
    (u.email || '').toLowerCase().includes(searchQuery.toLowerCase()) ||
    (u.id || '').toLowerCase().includes(searchQuery.toLowerCase())
  );

  return (
    <div className="w-full max-w-6xl mx-auto px-6 py-12">
      <div className="flex flex-col md:flex-row md:items-end justify-between mb-8 space-y-4 md:space-y-0">
        <div>
          <h1 className="text-3xl font-bold text-black dark:text-white tracking-tight mb-2 flex items-center space-x-3">
             <Shield className="w-8 h-8 text-orange-500" />
             <span>Admin Gateway</span>
          </h1>
          <p className="text-neutral-600 dark:text-neutral-400">Total Users: {usersList.length}</p>
        </div>
      </div>

      <div className="mb-8 flex items-center bg-white dark:bg-[#111111] border border-neutral-200 dark:border-neutral-800 rounded-xl px-4 py-2.5 shadow-sm max-w-md">
        <Search className="w-5 h-5 text-neutral-400 mr-3 shrink-0" />
        <input 
          type="text" 
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          placeholder="Search by Email or User ID..."
          className="bg-transparent border-none outline-none text-sm w-full text-black dark:text-white placeholder:text-neutral-500"
        />
      </div>

      <div className="bg-gray-50 dark:bg-[#111111] border border-neutral-200 dark:border-neutral-800 rounded-2xl overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="border-b border-neutral-200 dark:border-neutral-800 text-xs uppercase tracking-wider text-neutral-500 bg-gray-100/50 dark:bg-neutral-900/50">
                <th className="px-6 py-4 font-medium">User</th>
                <th className="px-6 py-4 font-medium">Plan</th>
                <th className="px-6 py-4 font-medium">Status</th>
                <th className="px-6 py-4 font-medium">Joined</th>
                <th className="px-6 py-4 font-medium text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
              {filteredUsers.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-6 py-12 text-center text-neutral-500">No users found.</td>
                </tr>
              ) : (
                filteredUsers.map((u) => (
                  <tr key={u.id} className="hover:bg-gray-100/50 dark:hover:bg-neutral-900/50 transition-colors">
                    <td className="px-6 py-4">
                      <div className="flex items-center space-x-3">
                        <div className="w-8 h-8 rounded-full bg-gray-200 dark:bg-neutral-800 flex items-center justify-center shrink-0">
                          <User className="w-4 h-4 text-neutral-500" />
                        </div>
                        <div className="min-w-0">
                          <div className="text-sm font-medium text-black dark:text-white truncate flex items-center space-x-2">
                            <span>{u.email}</span>
                            {u.role === 'admin' && (
                               <span className="px-1.5 py-0.5 rounded text-[9px] uppercase font-bold bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400">Admin</span>
                            )}
                          </div>
                          <div className="text-xs text-neutral-500 font-mono truncate">{u.id}</div>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <select 
                        value={u.planId || 'starter'} 
                        onChange={(e) => changeUserPlan(u.id, e.target.value)}
                        className="bg-transparent border border-neutral-300 dark:border-neutral-700 text-sm rounded-md px-2 py-1 outline-none focus:border-orange-500 text-black dark:text-white"
                      >
                        <option value="starter" className="dark:bg-neutral-900">Starter</option>
                        <option value="pro" className="dark:bg-neutral-900">Pro</option>
                        <option value="enterprise" className="dark:bg-neutral-900">Enterprise</option>
                      </select>
                    </td>
                    <td className="px-6 py-4">
                      <select 
                        value={u.status || 'active'} 
                        onChange={(e) => changeUserStatus(u.id, e.target.value)}
                        className={`bg-transparent border text-sm rounded-md px-2 py-1 outline-none ${u.status === 'active' ? 'border-green-500/30 text-green-600 dark:text-green-400' : 'border-red-500/30 text-red-600 dark:text-red-400'}`}
                      >
                        <option value="active" className="text-black dark:text-white dark:bg-neutral-900">Active</option>
                        <option value="suspended" className="text-black dark:text-white dark:bg-neutral-900">Suspended</option>
                      </select>
                    </td>
                    <td className="px-6 py-4 text-sm text-neutral-500">
                      {new Date(u.createdAt).toLocaleDateString()}
                    </td>
                    <td className="px-6 py-4 text-right">
                       <button
                         onClick={() => toggleAdmin(u.id, u.role !== 'admin')}
                         className="text-xs font-medium text-neutral-600 dark:text-neutral-400 hover:text-orange-600 dark:hover:text-orange-400 transition-colors"
                       >
                         {u.role === 'admin' ? 'Remove Admin' : 'Make Admin'}
                       </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
