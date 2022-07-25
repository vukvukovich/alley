import { BrowserRouter as Router, Switch, Route } from 'react-router-dom';
import { useState, useEffect, createContext, useContext } from 'react';
import { API_URL } from './utils';
import Header from './components/header';
import Home from './templates/Home';
import Single from './templates/Single';
import Footer from './components/footer';
import Category from './templates/Category';
import SignUp from './templates/SignUp';
import SignIn from './templates/SignIn';
import ResetPassword from './templates/ResetPassword';
import Order from './templates/Order';
import ErrorPage from './templates/ErrorPage';
import Orders from './templates/Orders';
import PrivateRoute from './components/utils/auth/PrivateRoute';
import Loader from './components/utils/Loader';
import OrderSuccessPage from './templates/OrderSuccessPage';
import OrderSingle from './templates/OrderSingle';
import UserSettings from './templates/UserSettings';
import SignUpSuccessPage from './templates/SignUpSuccessPage';

let AuthContext = createContext({});

const App = function () {
	const [state, setState] = useState({ loading: true, error: false });
	const token = localStorage.getItem('token');

	useEffect(() => {
		const fetchData = async () => {
			const headers = new Headers(),
				init = {
					method: 'GET',
					headers,
				};

			headers.append('Content-Type', 'application/json');

			if (token) {
				headers.append('x-auth', token);
			}

			let response;
			try {
				response = await fetch(
					API_URL + window.location.pathname,
					init
				);
			} catch (error) {
				// host not found, no connection, server not responding, etc...
				// setState({ error: true });
				return;
			}

			const { categories, category, slider, orders, order, vip, user } =
				await response.json();

			user.token = token;
			user.isVip = user.role === 'vip';
			console.log(`user`, user);
			AuthContext = createContext(user);

			setState({
				categories,
				category,
				slider,
				orders,
				order,
				vip,
				user,
				loading: false,
				error: !response.ok
			});
		};
		console.log('state', state);
		fetchData();
		// eslint-disable-next-line
	}, []);

	const user = useContext(AuthContext);

	if (state.loading) {
		return <Loader />;
	}

	if (state.error) {
		return (
			<AuthContext.Provider value={user}>
				<Header state={state} />
				<ErrorPage />
				<Footer />
			</AuthContext.Provider>
		);
	}

	return (
		<AuthContext.Provider value={user}>
			<Router>
				<Header state={state} />
				<Switch>
					<PrivateRoute exact path='/porudzbina-uspesna'>
						<OrderSuccessPage />
					</PrivateRoute>
					<Route exact path='/reset'>
						<ResetPassword />
					</Route>
					<Route exact path='/aktivacija/:id'>
						<SignUpSuccessPage />
					</Route>
					<Route exact path='/registracija'>
						<SignUp />
					</Route>
					<Route exact path='/registracija-vip'>
						<SignUp vip />
					</Route>
					<Route exact path='/prijava'>
						<SignIn />
					</Route>
					<PrivateRoute exact path='/korisnik'>
						<UserSettings />
					</PrivateRoute>
					<PrivateRoute exact path='/user/orders'>
						<Orders orders={state.orders} />
					</PrivateRoute>
					<PrivateRoute path='/user/order/:id'>
						<OrderSingle order={state.order} />
					</PrivateRoute>
					<Route path='/kategorije/:kategorija'>
						<Category category={state.category} vip={state.vip} />
					</Route>
					<Route exact path='/'>
						<Home state={state} />
					</Route>
					<PrivateRoute path='/:vip/naruci-klip'>
						<Order vip={state.vip} />
					</PrivateRoute>
					<Route path='/'>
						<Single vip={state.vip} />
					</Route>
				</Switch>
				<Footer />
			</Router>
		</AuthContext.Provider>
	);
};

export { App, AuthContext };
