# React Router Best Practices for Client-Side Routing

## Overview
This document outlines best practices for maintaining and extending the React Router implementation in our Najib Shop - Back Office. The current setup uses React Router v6 with nested routes, protected routes, and Material-UI integration.

## Current Architecture

### Router Configuration
- **BrowserRouter**: Used for clean URLs without hash fragments
- **Nested Routes**: Protected routes are nested under the main layout
- **Route Protection**: Authentication-based route guarding
- **Material-UI Integration**: Responsive navigation with drawer layout

### Key Components
- `App.tsx`: Main router configuration with all route definitions
- `Layout.tsx`: Main layout component with navigation and `<Outlet />`
- `ProtectedRoute.tsx`: Authentication guard for protected routes

## Best Practices

### 1. Route Organization

#### ✅ Do: Use Nested Routes for Related Features
```typescript
// Current implementation - Good
<Route path="/" element={<Layout />}>
  <Route index element={<Dashboard />} />
  <Route path="users" element={<Users />} />
  <Route path="users/new" element={<UserForm />} />
  <Route path="users/:id/edit" element={<UserForm />} />
</Route>
```

#### ✅ Do: Group Related Routes Logically
- Keep CRUD operations for the same resource together
- Use consistent URL patterns (`/resource`, `/resource/new`, `/resource/:id/edit`)
- Maintain alphabetical or logical ordering in route definitions

### 2. Route Protection

#### ✅ Do: Use ProtectedRoute for Authentication
```typescript
// Current implementation - Good
<Route path="/" element={
  <ProtectedRoute>
    <Layout />
  </ProtectedRoute>
}>
```

#### ✅ Do: Handle Loading States
```typescript
// Current implementation - Good
if (loading) {
  return <CircularProgress />;
}
```

#### ✅ Do: Preserve Intended Destination
```typescript
// Current implementation - Good
return <Navigate to="/login" state={{ from: location }} replace />;
```

### 3. Navigation

#### ✅ Do: Use useNavigate Hook
```typescript
// Current implementation - Good
const navigate = useNavigate();
const handleMenuClick = (path: string) => {
  navigate(path);
};
```

#### ✅ Do: Programmatic Navigation for Actions
- Use `navigate()` for form submissions
- Use `navigate(-1)` for back buttons
- Pass state for complex navigation scenarios

### 4. Route Parameters

#### ✅ Do: Use Descriptive Parameter Names
```typescript
// Good
<Route path="users/:id/edit" element={<UserForm />} />

// Avoid
<Route path="users/:userId/edit" element={<UserForm />} />
```

#### ✅ Do: Validate Route Parameters
```typescript
// In component
const { id } = useParams();
const userId = parseInt(id);

if (isNaN(userId)) {
  navigate('/users');
  return;
}
```

### 5. Code Splitting and Performance

#### ✅ Do: Implement Lazy Loading for Large Components
```typescript
const Users = lazy(() => import('./pages/users/Users'));
const UserForm = lazy(() => import('./pages/users/UserForm'));

// Wrap with Suspense
<Suspense fallback={<CircularProgress />}>
  <Routes>
    <Route path="users" element={<Users />} />
    <Route path="users/new" element={<UserForm />} />
  </Routes>
</Suspense>
```

#### ✅ Do: Use React Query for Data Fetching
```typescript
// Current implementation - Good
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes
      retry: 1,
    },
  },
});
```

### 6. Error Handling

#### ✅ Do: Implement Error Boundaries
```typescript
class ErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError(error) {
    return { hasError: true };
  }

  componentDidCatch(error, errorInfo) {
    // Log error
  }

  render() {
    if (this.state.hasError) {
      return <ErrorFallback />;
    }
    return this.props.children;
  }
}
```

#### ✅ Do: Handle 404 Routes
```typescript
// Add catch-all route
<Route path="*" element={<NotFound />} />
```

### 7. SEO and Accessibility

#### ✅ Do: Use Semantic HTML
- Use proper heading hierarchy
- Implement ARIA labels for navigation
- Ensure keyboard navigation works

#### ✅ Do: Handle Page Titles
```typescript
import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

function App() {
  const location = useLocation();

  useEffect(() => {
    const routeTitles = {
      '/': 'Dashboard',
      '/users': 'User Management',
      // ... other routes
    };

    document.title = `ERP - ${routeTitles[location.pathname] || 'Page'}`;
  }, [location]);
}
```

### 8. Testing

#### ✅ Do: Test Route Rendering
```typescript
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

test('renders users page', () => {
  render(
    <MemoryRouter initialEntries={['/users']}>
      <App />
    </MemoryRouter>
  );

  expect(screen.getByText('Users')).toBeInTheDocument();
});
```

#### ✅ Do: Test Navigation
```typescript
import { fireEvent } from '@testing-library/react';

test('navigates to user form', () => {
  render(<App />, { wrapper: MemoryRouter });

  fireEvent.click(screen.getByText('Add User'));
  expect(screen.getByText('New User')).toBeInTheDocument();
});
```

### 9. Maintenance Guidelines

#### Adding New Routes
1. Add route definition to `App.tsx`
2. Update navigation menu in `Layout.tsx` if needed
3. Create corresponding page component
4. Add TypeScript types for route parameters
5. Update tests

#### Route Naming Conventions
- Use lowercase for path segments
- Use kebab-case for multi-word segments
- Use plural for resource collections (`/users`, `/products`)
- Use singular for specific resources (`/users/:id`)

#### State Management
- Use URL parameters for filter/sort state
- Use query parameters for optional filters
- Avoid storing navigation state in global state

### 10. Common Pitfalls to Avoid

#### ❌ Don't: Use Absolute Paths in Components
```typescript
// Bad
<Link to="/users">Users</Link>

// Good - use relative paths when possible
<Link to="users">Users</Link>
```

#### ❌ Don't: Mix Route Types
```typescript
// Bad - mixing protected and public routes
<Route path="/login" element={<Login />} />
<Route path="/" element={<ProtectedRoute><Layout /></ProtectedRoute>} />
```

#### ❌ Don't: Forget to Handle Loading States
```typescript
// Bad - no loading state
if (!isAuthenticated) {
  return <Navigate to="/login" />;
}
```

#### ❌ Don't: Use Index Routes Incorrectly
```typescript
// Bad - index route with path
<Route path="dashboard" index element={<Dashboard />} />

// Good
<Route index element={<Dashboard />} />
```

## Migration Guide

### From React Router v5 to v6
If upgrading from v5, key changes:
- `Switch` → `Routes`
- `component`/`render` → `element`
- `useHistory` → `useNavigate`
- Nested routes require `<Outlet />`

### Performance Optimization
1. Implement code splitting for route components
2. Use `React.memo` for route components
3. Optimize re-renders with proper dependency arrays
4. Consider using `React.lazy` with `Suspense`

## Monitoring and Debugging

### Common Issues
1. **Route not rendering**: Check if parent route has `<Outlet />`
2. **Navigation not working**: Verify `useNavigate` is used correctly
3. **Protected routes not working**: Check authentication state
4. **Parameters undefined**: Ensure route parameters are properly typed

### Debugging Tools
- React Router DevTools
- Browser Network tab for route changes
- React DevTools for component tree inspection

## Conclusion

Following these best practices ensures:
- Maintainable and scalable routing architecture
- Good user experience with proper loading states
- SEO-friendly implementation
- Easy testing and debugging
- Performance optimization opportunities

Regularly review and refactor routes as the application grows to maintain clean, efficient routing.