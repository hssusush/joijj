import { useState, FormEvent } from 'react';
import { useNavigate } from 'react-router';
import { auth, db } from '../firebase';
import { signInWithPopup, GoogleAuthProvider, signInWithEmailAndPassword, createUserWithEmailAndPassword } from 'firebase/auth';
import { doc, setDoc, getDoc } from 'firebase/firestore';
import { KeyRound, Mail, Lock, Loader2, ArrowRight } from 'lucide-react';

export default function Login() {
  const navigate = useNavigate();
  const [isSignUp, setIsSignUp] = useState(false);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const initUserProfile = async (user: any) => {
    const userRef = doc(db, 'users', user.uid);
    const userDoc = await getDoc(userRef);
    if (!userDoc.exists()) {
      await setDoc(userRef, {
        email: user.email,
        planId: 'starter',
        status: 'active',
        createdAt: Date.now(),
        updatedAt: Date.now()
      });
    }
  };

  const handleGoogleLogin = async () => {
    try {
      const provider = new GoogleAuthProvider();
      const res = await signInWithPopup(auth, provider);
      await initUserProfile(res.user);
      navigate('/');
    } catch (err: any) {
      setError(err.message || 'Google sign in failed');
    }
  };

  const handleEmailAuth = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      if (isSignUp) {
        const res = await createUserWithEmailAndPassword(auth, email, password);
        await initUserProfile(res.user);
      } else {
        await signInWithEmailAndPassword(auth, email, password);
      }
      navigate('/');
    } catch (err: any) {
      setError(err.message || 'Authentication failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-[#0A0A0A] text-black dark:text-white flex flex-col items-center justify-center font-sans p-4">
      <div className="w-full max-w-md p-8 border border-neutral-200 dark:border-neutral-800 rounded-3xl bg-white dark:bg-[#111111] shadow-2xl relative overflow-hidden">
        
        {/* Decorative flair */}
        <div className="absolute top-0 right-0 w-32 h-32 bg-orange-500/10 blur-3xl rounded-full" />
        <div className="absolute bottom-0 left-0 w-32 h-32 bg-blue-500/10 blur-3xl rounded-full" />
        
        <div className="relative z-10 flex flex-col items-center">
          <div className="bg-orange-500/10 p-4 rounded-2xl border border-orange-500/20 mb-6">
             <KeyRound className="w-8 h-8 text-orange-500" />
          </div>
          <h1 className="text-3xl font-extrabold tracking-tight text-black dark:text-white mb-2">OpenClaw</h1>
          <p className="text-neutral-500 dark:text-neutral-400 mb-8 text-center text-sm px-4">
            {isSignUp ? 'Create a new account to generate license keys.' : 'Sign in to manage your AI agent licenses and gateway.'}
          </p>

          <button
            onClick={handleGoogleLogin}
            className="w-full flex items-center justify-center space-x-3 bg-white dark:bg-[#1A1A1A] text-black dark:text-white border border-neutral-200 dark:border-neutral-800 py-3 px-4 rounded-xl font-medium hover:bg-gray-50 dark:hover:bg-neutral-800 transition-colors shadow-sm"
          >
            <svg viewBox="0 0 24 24" className="w-5 h-5">
              <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
              <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
              <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
              <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            <span>Continue with Google</span>
          </button>

          <div className="w-full flex items-center my-6">
            <div className="flex-1 border-t border-neutral-200 dark:border-neutral-800"></div>
            <div className="px-4 text-xs tracking-wider text-neutral-400 uppercase font-medium">Or continue with email</div>
            <div className="flex-1 border-t border-neutral-200 dark:border-neutral-800"></div>
          </div>

          <form onSubmit={handleEmailAuth} className="w-full space-y-4">
            <div className="space-y-1">
              <label className="text-xs font-semibold text-neutral-600 dark:text-neutral-400 uppercase tracking-widest pl-1">Email</label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Mail className="h-5 w-5 text-neutral-400" />
                </div>
                <input
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="w-full bg-gray-50 dark:bg-black border border-neutral-200 dark:border-neutral-800 rounded-xl pl-10 pr-4 py-3 text-sm text-black dark:text-white placeholder:text-neutral-500 focus:outline-none focus:ring-2 focus:ring-orange-500/50 focus:border-orange-500 transition-all font-medium"
                  placeholder="name@example.com"
                />
              </div>
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold text-neutral-600 dark:text-neutral-400 uppercase tracking-widest pl-1">Password</label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Lock className="h-5 w-5 text-neutral-400" />
                </div>
                <input
                  type="password"
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="w-full bg-gray-50 dark:bg-black border border-neutral-200 dark:border-neutral-800 rounded-xl pl-10 pr-4 py-3 text-sm text-black dark:text-white placeholder:text-neutral-500 focus:outline-none focus:ring-2 focus:ring-orange-500/50 focus:border-orange-500 transition-all font-medium"
                  placeholder="••••••••"
                />
              </div>
            </div>

            {error && (
              <div className="text-sm text-red-500 bg-red-500/10 border border-red-500/20 rounded-lg p-3">
                {error}
              </div>
            )}

            <button
              type="submit"
              disabled={loading}
              className="w-full flex items-center justify-center space-x-2 bg-orange-500 hover:bg-orange-600 text-white py-3 px-4 rounded-xl font-bold transition-all shadow-md active:scale-[0.98] disabled:opacity-70 disabled:active:scale-100"
            >
              {loading ? (
                <Loader2 className="w-5 h-5 animate-spin" />
              ) : (
                <>
                  <span>{isSignUp ? 'Create Account' : 'Sign In'}</span>
                  <ArrowRight className="w-5 h-5" />
                </>
              )}
            </button>
          </form>

          <p className="mt-8 text-sm text-neutral-500 dark:text-neutral-400">
            {isSignUp ? 'Already have an account?' : "Don't have an account?"}{' '}
            <button
              onClick={() => {
                setIsSignUp(!isSignUp);
                setError('');
              }}
              className="text-orange-500 hover:text-orange-600 font-semibold transition-colors underline underline-offset-4"
            >
              {isSignUp ? 'Sign in instead' : 'Sign up for free'}
            </button>
          </p>
        </div>
      </div>
    </div>
  );
}
