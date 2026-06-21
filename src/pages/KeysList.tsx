import { useState, useEffect, useMemo } from 'react';
import { auth, db } from '../firebase';
import { collection, query, where, onSnapshot, doc, setDoc, updateDoc, deleteDoc, getDoc } from 'firebase/firestore';
import { KeyRound, CheckCircle2, XCircle, Plus, Calendar, Trash2, Edit2, TrendingUp, Loader2, Search, Copy } from 'lucide-react';

export default function KeysList() {
  const [keys, setKeys] = useState<any[]>([]);
  const [userProfile, setUserProfile] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingKey, setEditingKey] = useState<any>(null);
  const [formData, setFormData] = useState({ key: '', limit: '', expiryDays: 365 });
  const [searchQuery, setSearchQuery] = useState('');

  useEffect(() => {
    const uid = auth.currentUser?.uid;
    if (!uid) return;

    const unsubProfile = onSnapshot(doc(db, 'users', uid), (docSnap) => {
      if (docSnap.exists()) setUserProfile(docSnap.data());
    });

    const q = query(collection(db, 'license_keys'), where('userId', '==', uid));
    const unsubKeys = onSnapshot(q, (snapshot) => {
      const k = snapshot.docs.map(d => ({ id: d.id, ...d.data() }));
      setKeys(k);
      setLoading(false);
    });

    return () => {
      unsubProfile();
      unsubKeys();
    };
  }, []);

  const filteredKeys = useMemo(() => {
    if (!searchQuery.trim()) return keys;
    const lowerQuery = searchQuery.toLowerCase();
    return keys.filter(k => 
      k.key.toLowerCase().includes(lowerQuery) || 
      k.status.toLowerCase().includes(lowerQuery)
    );
  }, [keys, searchQuery]);

  const openCreateModal = () => {
    setEditingKey(null);
    setFormData({ key: '', limit: userProfile?.planId === 'pro' ? '-1' : '10000', expiryDays: 365 });
    setIsModalOpen(true);
  };

  const openEditModal = (k: any) => {
    setEditingKey(k);
    const msDiff = k.expiryDate ? k.expiryDate - Date.now() : 0;
    const days = msDiff > 0 ? Math.ceil(msDiff / (1000 * 60 * 60 * 24)) : 0;
    setFormData({ 
      key: k.key, 
      limit: k.limit?.toString() || '-1', 
      expiryDays: days || 365
    });
    setIsModalOpen(true);
  };

  const saveKey = async () => {
    const uid = auth.currentUser?.uid;
    if (!uid) return;
    
    let keyDocId = editingKey?.key || formData.key.trim() || ("oc_" + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15));
    const plan = userProfile?.planId || 'starter';
    const limit = parseInt(formData.limit, 10);
    
    const expiryDate = new Date();
    expiryDate.setDate(expiryDate.getDate() + (parseInt(formData.expiryDays.toString(), 10) || 0));

    try {
      if (editingKey) {
        await updateDoc(doc(db, 'license_keys', keyDocId), {
           limit,
           expiryDate: expiryDate.getTime(),
           updatedAt: Date.now()
        });
      } else {
        await setDoc(doc(db, 'license_keys', keyDocId), {
           userId: uid,
           key: keyDocId,
           planId: plan,
           status: 'active',
           limit: limit,
           requestsUsed: 0,
           expiryDate: expiryDate.getTime(),
           createdAt: Date.now(),
           updatedAt: Date.now()
        });
      }
      setIsModalOpen(false);
    } catch (e) {
      console.error(e);
      alert("Failed to save key. It might already exist or be invalid.");
    }
  };

  const deleteKey = async (id: string) => {
    if (confirm("Are you sure you want to delete this key? This action cannot be undone.")) {
      await deleteDoc(doc(db, 'license_keys', id));
    }
  };

  const toggleKeyStatus = async (id: string, currentStatus: string) => {
    await updateDoc(doc(db, 'license_keys', id), {
      status: currentStatus === 'active' ? 'revoked' : 'active',
      updatedAt: Date.now()
    });
  };

  if (loading) return <div className="min-h-screen bg-transparent flex items-center justify-center"><Loader2 className="w-6 h-6 text-orange-500 animate-spin" /></div>;

  return (
    <div className="w-full max-w-6xl mx-auto px-6 py-12">
      <div className="flex flex-col md:flex-row md:items-end justify-between mb-8 space-y-4 md:space-y-0">
        <div>
          <h1 className="text-3xl font-bold text-black dark:text-white tracking-tight mb-2">API Keys</h1>
          <p className="text-neutral-600 dark:text-neutral-400">Manage and generate API keys for your applications.</p>
        </div>
        <button 
          onClick={openCreateModal}
          className="bg-gray-200 dark:bg-neutral-800 hover:bg-gray-300 dark:hover:bg-neutral-700 text-black dark:text-white border border-neutral-300 dark:border-neutral-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center space-x-2"
        >
          <Plus className="w-4 h-4" />
          <span>Generate New Key</span>
        </button>
      </div>

      <div className="mb-8 flex items-center bg-white dark:bg-[#111111] border border-neutral-200 dark:border-neutral-800 rounded-xl px-4 py-2.5 shadow-sm max-w-md">
        <Search className="w-5 h-5 text-neutral-400 mr-3 shrink-0" />
        <input 
          type="text" 
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          placeholder="Search by Key ID or status..."
          className="bg-transparent border-none outline-none text-sm w-full text-black dark:text-white placeholder:text-neutral-500"
        />
      </div>

      {keys.some(k => k.limit !== -1 && k.limit !== undefined && ((k.requestsUsed || 0) / k.limit) > 0.8 && k.status === 'active') && (
        <div className="mb-8 bg-orange-50 dark:bg-orange-500/10 border border-orange-200 dark:border-orange-500/20 text-orange-800 dark:text-orange-400 p-4 rounded-xl flex items-start space-x-3">
           <TrendingUp className="w-5 h-5 mt-0.5 text-orange-600 dark:text-orange-400" />
           <div>
             <h4 className="font-medium text-sm">Quota Limit Approaching</h4>
             <p className="text-xs mt-1 dark:opacity-90">One or more of your active keys is nearing its API request limit.</p>
           </div>
        </div>
      )}

      <div className="bg-gray-50 dark:bg-[#111111] border border-neutral-200 dark:border-neutral-800 rounded-2xl p-6">
        {filteredKeys.length === 0 ? (
          <div className="text-center py-12 border border-dashed border-neutral-300 dark:border-neutral-800 rounded-xl">
            <KeyRound className="w-8 h-8 text-neutral-400 dark:text-neutral-600 mx-auto mb-3" />
            <p className="text-neutral-500 dark:text-neutral-400 text-sm">
              {keys.length === 0 ? 'No license keys generated yet.' : 'No keys match your search.'}
            </p>
          </div>
        ) : (
          <div className="space-y-4">
            {filteredKeys.map((k) => {
              const isExpired = k.expiryDate && Date.now() > k.expiryDate;
              const isOverLimit = k.limit !== -1 && (k.requestsUsed || 0) >= k.limit;
              
              let displayStatus = k.status;
              if (k.status === 'active') {
                 if (isExpired) displayStatus = 'expired';
                 else if (isOverLimit) displayStatus = 'exhausted';
              }

              return (
                <div key={k.id} className="flex flex-col sm:flex-row sm:items-center justify-between p-4 bg-white dark:bg-[#0a0a0a] border border-neutral-200 dark:border-neutral-800/80 rounded-xl hover:border-neutral-300 dark:hover:border-neutral-700 transition-colors group tracking-tight">
                  <div className="mb-4 sm:mb-0 text-left flex-1 mr-4">
                    <div className="font-mono text-sm text-neutral-800 dark:text-neutral-200 mb-1 break-all">{k.key}</div>
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs mb-3">
                      <span className="text-neutral-500 flex items-center">
                        <Calendar className="w-3.5 h-3.5 mr-1.5" />
                         Created {new Date(k.createdAt).toLocaleDateString()}
                      </span>
                      {k.expiryDate && (
                        <span className={`flex items-center ${isExpired ? 'text-red-600 dark:text-red-400 font-medium' : 'text-neutral-500'}`}>
                           <Calendar className="w-3.5 h-3.5 mr-1.5" />
                           Expires {new Date(k.expiryDate).toLocaleDateString()}
                        </span>
                      )}
                      <span className={`flex items-center space-x-1.5 ${
                         displayStatus === 'active' ? 'text-green-600 dark:text-green-500' : 
                         displayStatus === 'expired' || displayStatus === 'exhausted' ? 'text-orange-600 dark:text-orange-500' : 'text-red-600 dark:text-red-400'
                      }`}>
                         {displayStatus === 'active' ? <CheckCircle2 className="w-3.5 h-3.5" /> : <XCircle className="w-3.5 h-3.5" />}
                         <span className="uppercase tracking-wider text-[10px] font-bold">{displayStatus}</span>
                      </span>
                    </div>
                    
                    <div className="w-full max-w-sm mt-3">
                      <div className="flex justify-between text-xs text-neutral-500 dark:text-neutral-400 mb-1.5">
                        <span>API Requests Used</span>
                        <span>{k.requestsUsed || 0} / {k.limit === -1 || k.limit === undefined ? 'Unlimited' : k.limit}</span>
                      </div>
                      <div className="w-full bg-gray-200 dark:bg-neutral-800 rounded-full h-1.5 overflow-hidden">
                        <div 
                          className={`h-full rounded-full transition-all ${isOverLimit ? 'bg-red-500' : (((k.requestsUsed || 0) / (k.limit === -1 || !k.limit ? 1 : k.limit)) > 0.8 ? 'bg-orange-500' : 'bg-green-500')}`} 
                          style={{ width: k.limit === -1 || k.limit === undefined ? '100%' : `${Math.min(100, ((k.requestsUsed || 0) / k.limit) * 100)}%` }}
                         />
                      </div>
                    </div>
                  </div>
                  
                  <div className="flex items-center gap-2 shrink-0 flex-wrap justify-end">
                     <button 
                       onClick={() => navigator.clipboard.writeText(k.key)}
                       className="text-xs text-neutral-600 dark:text-neutral-400 hover:text-black dark:hover:text-white px-3 py-1.5 rounded bg-gray-100 dark:bg-neutral-800/50 hover:bg-gray-200 dark:hover:bg-neutral-800 transition-colors shadow-sm flex items-center space-x-1"
                       title="Copy to Clipboard"
                     >
                       <Copy className="w-3.5 h-3.5" />
                       <span>Copy</span>
                     </button>
                     <button 
                       onClick={() => openEditModal(k)}
                       className="text-xs px-3 py-1.5 rounded bg-gray-100 dark:bg-neutral-800/50 hover:bg-gray-200 dark:hover:bg-neutral-800 transition-colors text-neutral-600 dark:text-neutral-400 hover:text-black dark:hover:text-white flex items-center shadow-sm"
                     >
                       <Edit2 className="w-3.5 h-3.5" />
                     </button>
                     <button 
                       onClick={() => toggleKeyStatus(k.id, k.status)}
                       className={`text-xs px-3 py-1.5 rounded font-medium transition-colors shadow-sm ${
                         k.status === 'active' 
                            ? 'text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-400/10 hover:bg-red-200 dark:hover:bg-red-400/20' 
                            : 'text-green-600 dark:text-green-400 bg-green-100 dark:bg-green-400/10 hover:bg-green-200 dark:hover:bg-green-400/20'
                       }`}
                     >
                       {k.status === 'active' ? 'Revoke' : 'Activate'}
                     </button>
                     <button 
                       onClick={() => deleteKey(k.id)}
                       className="p-1.5 rounded text-red-500/70 hover:text-red-500 hover:bg-red-500/10 transition-colors ml-1"
                       title="Delete Key"
                     >
                       <Trash2 className="w-4 h-4" />
                     </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {isModalOpen && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className="bg-white dark:bg-[#111111] border border-neutral-200 dark:border-neutral-800 rounded-2xl p-6 w-full max-w-md shadow-2xl">
            <h3 className="text-xl font-bold text-black dark:text-white mb-4">
              {editingKey ? 'Edit License Key' : 'Create Custom Key'}
            </h3>
            
            <div className="space-y-4">
              {!editingKey && (
                <div>
                  <label className="block text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-1">Custom Key (optional)</label>
                  <input 
                    type="text" 
                    value={formData.key}
                    onChange={(e) => setFormData({...formData, key: e.target.value})}
                    placeholder="Leave blank to auto-generate"
                    className="w-full bg-gray-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-lg px-3 py-2.5 text-black dark:text-white focus:outline-none focus:border-orange-500 font-mono text-sm"
                  />
                </div>
              )}
              
              <div>
                <label className="block text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-1">API Request Limit (-1 for unlimited)</label>
                <input 
                  type="number" 
                  value={formData.limit}
                  onChange={(e) => setFormData({...formData, limit: e.target.value})}
                  className="w-full bg-gray-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-lg px-3 py-2.5 text-black dark:text-white focus:outline-none focus:border-orange-500 font-mono text-sm"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-neutral-600 dark:text-neutral-400 mb-1">Expiry Date (Days from now)</label>
                <input 
                  type="number" 
                  value={formData.expiryDays}
                  onChange={(e) => setFormData({...formData, expiryDays: parseInt(e.target.value) || 0})}
                  className="w-full bg-gray-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-lg px-3 py-2.5 text-black dark:text-white focus:outline-none focus:border-orange-500 font-mono text-sm"
                />
              </div>
            </div>
            
            <div className="flex items-center justify-end space-x-3 mt-8">
              <button 
                onClick={() => setIsModalOpen(false)}
                className="px-4 py-2 text-sm font-medium text-neutral-600 dark:text-neutral-400 hover:bg-gray-100 dark:hover:bg-neutral-900 rounded-lg transition-colors"
              >
                Cancel
              </button>
              <button 
                onClick={saveKey}
                className="px-4 py-2 text-sm font-medium bg-black dark:bg-white text-white dark:text-black rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-200 transition-colors"
              >
                {editingKey ? 'Save Changes' : 'Generate Key'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
