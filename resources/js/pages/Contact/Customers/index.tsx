import ContactList from '../ContactList';
import type { Contact } from '../types';

type Props = {
    contacts: Contact[];
};

export default function Index({ contacts }: Props) {
    return <ContactList contacts={contacts} type="customers" />;
}
