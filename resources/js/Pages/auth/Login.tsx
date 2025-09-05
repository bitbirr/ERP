import React, { useState, useCallback } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import {
  Container,
  Paper,
  TextField,
  Button,
  Typography,
  Box,
  Alert,
  CircularProgress,
  Snackbar,
  IconButton,
} from '@mui/material';
import { Close as CloseIcon } from '@mui/icons-material';
import { useAuth } from '../../contexts/AuthContext';

// Error types for better categorization
enum ErrorType {
  NETWORK = 'network',
  AUTHENTICATION = 'authentication',
  VALIDATION = 'validation',
  SERVER = 'server',
  TIMEOUT = 'timeout',
  UNKNOWN = 'unknown'
}

interface LoginError {
  type: ErrorType;
  message: string;
  retryable: boolean;
}

const Login: React.FC = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<LoginError | null>(null);
  const [loading, setLoading] = useState(false);
  const [retryCount, setRetryCount] = useState(0);
  const [formErrors, setFormErrors] = useState<{email?: string; password?: string}>({});

  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const from = location.state?.from?.pathname || '/';
  const MAX_RETRIES = 3;

  // Enhanced error categorization
  const categorizeError = useCallback((err: any): LoginError => {
    if (!err) {
      return { type: ErrorType.UNKNOWN, message: 'An unexpected error occurred', retryable: false };
    }

    // Network errors
    if (!navigator.onLine) {
      return { type: ErrorType.NETWORK, message: 'No internet connection. Please check your network and try again.', retryable: true };
    }

    if (err.code === 'NETWORK_ERROR' || err.message?.includes('Network Error')) {
      return { type: ErrorType.NETWORK, message: 'Unable to connect to the server. Please check your connection and try again.', retryable: true };
    }

    // Timeout errors
    if (err.code === 'ECONNABORTED' || err.message?.includes('timeout')) {
      return { type: ErrorType.TIMEOUT, message: 'Request timed out. Please try again.', retryable: true };
    }

    // Authentication errors (401, 403)
    if (err.response?.status === 401 || err.response?.status === 403) {
      return { type: ErrorType.AUTHENTICATION, message: 'Invalid email or password. Please check your credentials and try again.', retryable: false };
    }

    // Validation errors (400)
    if (err.response?.status === 400) {
      const serverMessage = err.response?.data?.message;
      if (serverMessage?.includes('email') || serverMessage?.includes('password')) {
        return { type: ErrorType.VALIDATION, message: serverMessage || 'Please check your input and try again.', retryable: false };
      }
    }

    // Server errors (5xx)
    if (err.response?.status >= 500) {
      return { type: ErrorType.SERVER, message: 'Server error. Please try again later.', retryable: true };
    }

    // Use server message if available
    if (err.response?.data?.message) {
      return { type: ErrorType.UNKNOWN, message: err.response.data.message, retryable: false };
    }

    // Default error
    return { type: ErrorType.UNKNOWN, message: err.message || 'Login failed. Please try again.', retryable: false };
  }, []);

  // Form validation
  const validateForm = useCallback((): boolean => {
    const errors: {email?: string; password?: string} = {};

    if (!email.trim()) {
      errors.email = 'Email is required';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      errors.email = 'Please enter a valid email address';
    }

    if (!password.trim()) {
      errors.password = 'Password is required';
    } else if (password.length < 6) {
      errors.password = 'Password must be at least 6 characters';
    }

    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  }, [email, password]);

  // Clear errors when user starts typing
  const handleEmailChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setEmail(e.target.value);
    if (formErrors.email) {
      setFormErrors(prev => ({ ...prev, email: undefined }));
    }
    if (error) setError(null);
  };

  const handlePasswordChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setPassword(e.target.value);
    if (formErrors.password) {
      setFormErrors(prev => ({ ...prev, password: undefined }));
    }
    if (error) setError(null);
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();

    if (!validateForm()) {
      return;
    }

    setError(null);
    setLoading(true);

    try {
      await login(email, password);
      navigate(from, { replace: true });
      setRetryCount(0); // Reset retry count on success
    } catch (err: any) {
      const loginError = categorizeError(err);
      setError(loginError);

      // Auto-retry for network errors (up to MAX_RETRIES)
      if (loginError.retryable && retryCount < MAX_RETRIES) {
        setTimeout(() => {
          setRetryCount(prev => prev + 1);
          handleSubmit(event);
        }, 2000 * (retryCount + 1)); // Exponential backoff
      }
    } finally {
      setLoading(false);
    }
  };

  const handleRetry = () => {
    if (error?.retryable) {
      setError(null);
      setRetryCount(0);
      // Trigger form submission again
      const form = document.getElementById('login-form') as HTMLFormElement;
      if (form) form.requestSubmit();
    }
  };

  const handleCloseError = () => {
    setError(null);
  };

  return (
    <Container component="main" maxWidth="sm">
      <Box
        sx={{
          marginTop: 8,
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
        }}
      >
        <Paper
          elevation={3}
          sx={{
            padding: 4,
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            width: '100%',
          }}
        >
          <Typography component="h1" variant="h4" gutterBottom>
            Najib Shop - Back Office Login
          </Typography>

          {/* Enhanced Error Display */}
          {error && (
            <Alert
              severity={error.type === ErrorType.NETWORK || error.type === ErrorType.TIMEOUT ? "warning" : "error"}
              sx={{ width: '100%', mb: 2 }}
              action={
                error.retryable ? (
                  <Button
                    color="inherit"
                    size="small"
                    onClick={handleRetry}
                    disabled={loading}
                  >
                    Retry
                  </Button>
                ) : (
                  <IconButton
                    aria-label="close"
                    color="inherit"
                    size="small"
                    onClick={handleCloseError}
                  >
                    <CloseIcon fontSize="inherit" />
                  </IconButton>
                )
              }
            >
              {error.message}
              {error.retryable && retryCount > 0 && (
                <Typography variant="caption" display="block" sx={{ mt: 1 }}>
                  Retry attempt {retryCount} of {MAX_RETRIES}
                </Typography>
              )}
            </Alert>
          )}

          <Box
            id="login-form"
            component="form"
            onSubmit={handleSubmit}
            sx={{ mt: 1, width: '100%' }}
          >
            <TextField
              margin="normal"
              required
              fullWidth
              id="email"
              label="Email Address"
              name="email"
              autoComplete="email"
              autoFocus
              value={email}
              onChange={handleEmailChange}
              disabled={loading}
              error={!!formErrors.email}
              helperText={formErrors.email}
            />
            <TextField
              margin="normal"
              required
              fullWidth
              name="password"
              label="Password"
              type="password"
              id="password"
              autoComplete="current-password"
              value={password}
              onChange={handlePasswordChange}
              disabled={loading}
              error={!!formErrors.password}
              helperText={formErrors.password}
            />
            <Button
              type="submit"
              fullWidth
              variant="contained"
              sx={{ mt: 3, mb: 2 }}
              disabled={loading || !!error}
            >
              {loading ? (
                <>
                  <CircularProgress size={20} sx={{ mr: 1 }} />
                  {retryCount > 0 ? `Retrying... (${retryCount}/${MAX_RETRIES})` : 'Signing In...'}
                </>
              ) : (
                'Sign In'
              )}
            </Button>
          </Box>
        </Paper>
      </Box>

      {/* Network Status Snackbar */}
      <Snackbar
        open={!navigator.onLine}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'center' }}
      >
        <Alert severity="warning" sx={{ width: '100%' }}>
          No internet connection. Please check your network.
        </Alert>
      </Snackbar>
    </Container>
  );
};

export default Login;