import { ReactSortable } from 'react-sortablejs';
import Installment from './Installment';

export default function Installments({ list, setList }) {
  return list.length == 0 ? (
    <div className="installments">
      <h2>No hay cuotas disponibles</h2>
    </div>
  ) : (
    <ReactSortable
      className="installments"
      list={list}
      setList={setList}
      animation={150}
    >
      {list.map((installment, index) => (
        <Installment
          {...installment}
          key={installment.uid}
          index={index}
          list={list}
          setList={setList}
        />
      ))}
    </ReactSortable>
  );
}
