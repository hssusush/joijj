import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router';
import { auth } from '../firebase';
import { onAuthStateChanged, User } from 'firebase/auth';

export default function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  useEffect(() => {
    return onAuthStateChanged(auth, (usr) => {
      if (!usr) {
        navigate('/login');
      } else {
        setUser(usr);
      }
      setLoading(false);
    });
  }, [navigate]);

  if (loading) return <div className="min-h-screen bg-gray-50 dark:bg-[#0A0A0A] flex items-center justify-center text-neutral-400">Loading...</div>;
  return user ? <>{children}</> : null;
}
